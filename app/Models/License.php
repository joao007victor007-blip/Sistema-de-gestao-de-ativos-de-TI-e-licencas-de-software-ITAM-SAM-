<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\LicenseAssignmentType;
use App\Enums\LicenseType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Licença de software.
 *
 * A coluna expires_at tem dois significados:
 * - renovação automática (assinatura mensal ou anual): data da PRÓXIMA COBRANÇA,
 *   avançada sozinha pelo comando diário quando passa;
 * - demais casos: data de VENCIMENTO do contrato.
 */
class License extends Model
{
    use Auditable, HasFactory;

    // A chave (license_key) fica FORA do $fillable: só é gravada de forma explícita no controlador
    protected $fillable = [
        'software_id', 'name', 'license_type', 'assignment_type', 'seats', 'admin_account_email',
        'purchase_date', 'expires_at', 'auto_renew', 'billing_cycle', 'cost', 'supplier',
        'invoice_number', 'alert_days', 'notes',
    ];

    // Nunca incluir a chave quando o modelo for convertido para JSON ou array
    protected $hidden = ['license_key'];

    protected function casts(): array
    {
        return [
            'license_type' => LicenseType::class,
            'assignment_type' => LicenseAssignmentType::class,
            'billing_cycle' => BillingCycle::class,
            'license_key' => 'encrypted',
            'purchase_date' => 'date',
            'expires_at' => 'date',
            'retired_at' => 'date',
            'auto_renew' => 'boolean',
            'cost' => 'decimal:2',
            'seats' => 'integer',
            'alert_days' => 'integer',
        ];
    }

    public function software(): BelongsTo
    {
        return $this->belongsTo(Software::class);
    }

    /** Todo o histórico de atribuições */
    public function assignments(): HasMany
    {
        return $this->hasMany(LicenseAssignment::class);
    }

    /** Somente as atribuições em uso (ocupando vagas) */
    public function activeAssignments(): HasMany
    {
        return $this->hasMany(LicenseAssignment::class)->whereNull('revoked_at');
    }

        /** Notas fiscais, contratos e outros documentos */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    // ------------------------------------------------------------------
    // Filtros reutilizáveis
    // ------------------------------------------------------------------

    /** Licenças que não foram baixadas */
    public function scopeNotRetired(Builder $query): Builder
    {
        return $query->whereNull('retired_at');
    }

    /** Assinaturas com renovação automática mensal ou anual */
    public function scopeAutoRenewing(Builder $query): Builder
    {
        return $query->where('license_type', LicenseType::Assinatura->value)
            ->where('auto_renew', true)
            ->whereIn('billing_cycle', [BillingCycle::Mensal->value, BillingCycle::Anual->value]);
    }

    /** Todas as demais (renovação manual, perpétuas, OEM) */
    public function scopeNotAutoRenewing(Builder $query): Builder
    {
        return $query->where(function (Builder $sub) {
            $sub->where('license_type', '!=', LicenseType::Assinatura->value)
                ->orWhere('auto_renew', false)
                ->orWhereNull('billing_cycle')
                ->orWhereNotIn('billing_cycle', [BillingCycle::Mensal->value, BillingCycle::Anual->value]);
        });
    }

    /** Licenças vencidas: só as de renovação manual podem vencer */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->notRetired()
            ->notAutoRenewing()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', today()->toDateString());
    }

    /** Renovação manual dentro do prazo de aviso */
    public function scopeExpiringSoon(Builder $query): Builder
    {
        $today = today()->toDateString();

        return $query->notRetired()
            ->notAutoRenewing()
            ->where('expires_at', '>=', $today)
            ->whereRaw('expires_at <= DATE_ADD(?, INTERVAL alert_days DAY)', [$today]);
    }

    /** Renovação automática dentro do prazo de aviso */
    public function scopeRenewingSoon(Builder $query): Builder
    {
        return $query->notRetired()
            ->autoRenewing()
            ->whereNotNull('expires_at')
            ->whereRaw('expires_at <= DATE_ADD(?, INTERVAL alert_days DAY)', [today()->toDateString()]);
    }

    // ------------------------------------------------------------------
    // Regras da licença
    // ------------------------------------------------------------------

    public function isRetired(): bool
    {
        return $this->retired_at !== null;
    }

    public function isAutoRenewing(): bool
    {
        return $this->license_type === LicenseType::Assinatura
            && $this->auto_renew
            && in_array($this->billing_cycle, [BillingCycle::Mensal, BillingCycle::Anual], true);
    }

    /** Próxima data de cobrança a partir de hoje, avançando ciclos já passados */
    public function nextRenewalDate(?Carbon $today = null): Carbon
    {
        $today ??= today();
        $date = $this->expires_at->copy();

        while ($date->lt($today)) {
            $date = $this->billing_cycle === BillingCycle::Mensal
                ? $date->addMonthNoOverflow()
                : $date->addYearNoOverflow();
        }

        return $date;
    }

    /** Verifica se há chave sem precisar descriptografá-la */
    public function hasKey(): bool
    {
        return ! empty($this->attributes['license_key'] ?? null);
    }

    /** Texto da quantidade contratada, usado nas telas */
    public function seatsLabel(): string
    {
        if (! $this->assignment_type->usesSeats()) {
            return 'Uso corporativo';
        }

        return $this->seats . ($this->seats === 1 ? ' licença' : ' licenças');
    }

    /** "Renova em 10/12/2026 (mensal)" ou "Vence em 31/03/2027" */
    public function dateLabel(): ?string
    {
        if ($this->expires_at === null) {
            return null;
        }

        if ($this->isAutoRenewing()) {
            return 'Renova em ' . $this->nextRenewalDate()->format('d/m/Y')
                . ' (' . mb_strtolower($this->billing_cycle->label()) . ')';
        }

        return 'Vence em ' . $this->expires_at->format('d/m/Y');
    }

    /** Motivo pelo qual a licença não aceita novas atribuições, ou null se aceita */
    public function assignmentBlockReason(): ?string
    {
        if (! $this->assignment_type->usesSeats()) {
            return 'Licenças corporativas não são atribuídas individualmente.';
        }

        if ($this->isRetired()) {
            return 'Esta licença foi baixada e não aceita novas atribuições.';
        }

        if ($this->expirationState() === 'vencida') {
            return 'Esta licença está vencida. Renove o contrato antes de atribuí-la.';
        }

        return null;
    }

    /** baixada, sem_vencimento, renova_em_breve, renovacao_automatica, vencida, vencendo ou vigente */
    public function expirationState(): string
    {
        if ($this->isRetired()) {
            return 'baixada';
        }

        if ($this->expires_at === null) {
            return 'sem_vencimento';
        }

        if ($this->isAutoRenewing()) {
            return $this->nextRenewalDate()->lte(today()->addDays($this->alert_days))
                ? 'renova_em_breve'
                : 'renovacao_automatica';
        }

        if ($this->expires_at->lt(today())) {
            return 'vencida';
        }

        if ($this->expires_at->lte(today()->addDays($this->alert_days))) {
            return 'vencendo';
        }

        return 'vigente';
    }

    public function expirationLabel(): string
    {
        return match ($this->expirationState()) {
            'baixada' => 'Baixada',
            'sem_vencimento' => 'Sem vencimento',
            'renova_em_breve' => 'Renova em breve',
            'renovacao_automatica' => 'Renovação automática',
            'vencida' => 'Vencida',
            'vencendo' => 'Vence em breve',
            default => 'Vigente',
        };
    }
}