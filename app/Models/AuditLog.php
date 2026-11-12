<?php

namespace App\Models;

use App\Enums\AssetStatus;
use App\Enums\AuditAction;
use App\Enums\BillingCycle;
use App\Enums\LicenseAssignmentType;
use App\Enums\LicenseType;
use App\Enums\LocationType;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

class AuditLog extends Model
{
    // Não existe "updated_at": registros de auditoria não são atualizados
    public const UPDATED_AT = null;

    // Nenhum campo é preenchível por formulário: só o Audit::record grava registros
    protected $fillable = [];

    /** Itens que podem ser filtrados na tela de auditoria */
    public const AUDITABLE_TYPES = [
        Asset::class,
        License::class,
        Employee::class,
        Location::class,
        Department::class,
        Category::class,
        Manufacturer::class,
        Software::class,
        User::class,
    ];

    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Registros de auditoria não podem ser alterados.'));
        static::deleting(fn () => throw new LogicException('Registros de auditoria não podem ser apagados.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Nome em português do tipo de item, usado nas descrições */
    public static function entityName(string $class): string
    {
        return match (class_basename($class)) {
            'Asset' => 'ativo',
            'License' => 'licença',
            'Employee' => 'colaborador',
            'Location' => 'local',
            'Department' => 'setor',
            'Category' => 'categoria',
            'Manufacturer' => 'fabricante',
            'Software' => 'software',
            'User' => 'usuário',
            default => mb_strtolower(class_basename($class)),
        };
    }

    /** Quem fez a ação, para exibição */
    public function actorName(): string
    {
        if ($this->user) {
            return $this->user->name;
        }

        return $this->action === AuditAction::LoginFailed ? 'Não identificado' : 'Sistema';
    }

    /** Endereço da página do item afetado, quando houver */
    public function subjectUrl(): ?string
    {
        if (! $this->subject_type || ! $this->subject_id) {
            return null;
        }

        return match ($this->subject_type) {
            Asset::class => route('assets.show', $this->subject_id),
            License::class => route('licenses.show', $this->subject_id),
            Employee::class => route('employees.show', $this->subject_id),
            Location::class => route('locations.edit', $this->subject_id),
            Department::class => route('departments.edit', $this->subject_id),
            Category::class => route('categories.edit', $this->subject_id),
            Manufacturer::class => route('manufacturers.edit', $this->subject_id),
            Software::class => route('software.edit', $this->subject_id),
            User::class => route('users.edit', $this->subject_id),
            default => null,
        };
    }

    /** Nome do campo em português */
    public static function fieldLabel(string $field): string
    {
        return match ($field) {
            'name' => 'Nome',
            'email' => 'E-mail',
            'email_verified_at' => 'E-mail verificado em',
            'role' => 'Papel',
            'is_active' => 'Ativo',
            'must_change_password' => 'Troca de senha obrigatória',
            'code' => 'Código interno',
            'asset_tag' => 'Nº de patrimônio',
            'model' => 'Modelo',
            'serial_number' => 'Número de série',
            'description' => 'Descrição',
            'category_id' => 'Categoria (nº)',
            'manufacturer_id' => 'Fabricante (nº)',
            'location_id' => 'Local (nº)',
            'department_id' => 'Setor (nº)',
            'software_id' => 'Software (nº)',
            'status', 'situacao' => 'Situação',
            'purchase_date' => 'Data de compra',
            'purchase_price' => 'Valor de compra',
            'warranty_until' => 'Fim da garantia',
            'invoice_number' => 'Nota fiscal',
            'supplier' => 'Fornecedor',
            'notes' => 'Observações',
            'registration' => 'Matrícula',
            'job_title' => 'Cargo',
            'terminated_at' => 'Data de desligamento',
            'license_type' => 'Tipo de licença',
            'assignment_type' => 'Forma de atribuição',
            'seats' => 'Licenças contratadas',
            'license_key' => 'Chave de ativação',
            'expires_at' => 'Vencimento',
            'alert_days' => 'Dias de alerta',
            'cost' => 'Valor',
            'billing_cycle' => 'Periodicidade',
            'auto_renew' => 'Renovação automática',
            'admin_account_email' => 'Conta administradora',
            'type' => 'Tipo',
            'city' => 'Cidade',
            'state' => 'UF',
            'colaborador' => 'Colaborador',
            'equipamento' => 'Equipamento',
            'data' => 'Data',
            'termo' => 'Termo',
            'condicao' => 'Condição',
            'motivo' => 'Motivo',
            'licencas_liberadas' => 'Licenças liberadas',
            'equipamentos_pendentes' => 'Equipamentos pendentes',
            default => $field,
        };
    }

    /** Valor formatado para exibição: datas, opções e sim/não em português */
    public static function formatValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        if (in_array($field, ['is_active', 'auto_renew', 'must_change_password', 'term_signed'], true)) {
            return (string) $value === '1' ? 'Sim' : 'Não';
        }

        $enum = match ($field) {
            'status', 'situacao' => AssetStatus::tryFrom((string) $value),
            'role' => UserRole::tryFrom((string) $value),
            'license_type' => LicenseType::tryFrom((string) $value),
            'assignment_type' => LicenseAssignmentType::tryFrom((string) $value),
            'billing_cycle' => BillingCycle::tryFrom((string) $value),
            'type' => LocationType::tryFrom((string) $value),
            default => null,
        };

        if ($enum !== null) {
            return $enum->label();
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            $date = Carbon::parse($value);

            return $date->format('H:i:s') === '00:00:00' ? $date->format('d/m/Y') : $date->format('d/m/Y H:i');
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }
}