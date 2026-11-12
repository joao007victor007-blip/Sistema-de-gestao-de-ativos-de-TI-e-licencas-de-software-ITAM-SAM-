<?php

namespace App\Support;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Ponto único de gravação da auditoria.
 * Uso: Audit::record(AuditAction::Checkout, $asset, 'Entregou ... a ...', [...]);
 */
class Audit
{
    /**
     * @param  array<string, array{de?: mixed, para?: mixed}>|null  $changes
     */
    public static function record(
        AuditAction $action,
        ?Model $subject,
        string $description,
        ?array $changes = null,
        ?int $userId = null,
    ): AuditLog {
        $inConsole = app()->runningInConsole();

        $log = new AuditLog();
        $log->forceFill([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'description' => Str::limit($description, 250),
            'changes' => $changes ?: null,
            'ip_address' => $inConsole ? null : request()->ip(),
            'user_agent' => $inConsole ? null : Str::limit((string) request()->userAgent(), 250, ''),
            'created_at' => now(),
        ]);
        $log->save();

        return $log;
    }
}