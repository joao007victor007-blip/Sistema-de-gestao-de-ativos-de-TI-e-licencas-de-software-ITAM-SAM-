<?php

namespace App\Models\Concerns;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;

/**
 * Registra automaticamente o cadastro e as alterações do modelo na auditoria.
 * Ações de negócio (entrega, descarte...) usam saveQuietly() e registram um evento próprio.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            Audit::record(
                AuditAction::Created,
                $model,
                'Cadastrou ' . AuditLog::entityName($model::class) . ' ' . $model->auditLabel(),
                $model->auditSnapshot(),
            );
        });

        static::updated(function (Model $model) {
            $changes = $model->auditDiff();

            if ($changes === []) {
                return;
            }

            Audit::record(
                AuditAction::Updated,
                $model,
                'Alterou ' . AuditLog::entityName($model::class) . ' ' . $model->auditLabel(),
                $changes,
            );
        });
    }

    /** Como o item aparece nas descrições. Os modelos podem personalizar. */
    public function auditLabel(): string
    {
        return (string) ($this->getAttribute('name') ?? '#' . $this->getKey());
    }

    /** Campos que nunca entram na auditoria */
    protected function auditIgnoredFields(): array
    {
        return ['id', 'password', 'remember_token', 'created_at', 'updated_at', 'last_login_at'];
    }

    /** Campos sensíveis: registra que mudaram, nunca o valor */
    protected function auditMaskedFields(): array
    {
        return ['license_key'];
    }

    protected function auditSnapshot(): array
    {
        $snapshot = [];

        foreach ($this->getAttributes() as $field => $value) {
            if ($value === null || in_array($field, $this->auditIgnoredFields(), true)) {
                continue;
            }

            $snapshot[$field] = in_array($field, $this->auditMaskedFields(), true)
                ? ['para' => '[informada]']
                : ['para' => $value];
        }

        return $snapshot;
    }

    protected function auditDiff(): array
    {
        $diff = [];

        foreach ($this->getChanges() as $field => $newValue) {
            if (in_array($field, $this->auditIgnoredFields(), true)) {
                continue;
            }

            $diff[$field] = in_array($field, $this->auditMaskedFields(), true)
                ? ['de' => '[oculta]', 'para' => '[alterada]']
                : ['de' => $this->getRawOriginal($field), 'para' => $newValue];
        }

        return $diff;
    }
}