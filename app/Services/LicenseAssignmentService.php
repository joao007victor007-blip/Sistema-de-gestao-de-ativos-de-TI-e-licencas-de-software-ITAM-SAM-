<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\LicenseAssignmentType;
use App\Exceptions\MovementException;
use App\Models\Asset;
use App\Models\Employee;
use App\Models\License;
use App\Models\LicenseAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Regras de atribuição e liberação de licenças.
 * A licença é travada durante a operação, para que duas atribuições simultâneas
 * não ultrapassem a quantidade contratada.
 */
class LicenseAssignmentService
{
    public function assign(License $license, User $by, array $data): LicenseAssignment
    {
        return DB::transaction(function () use ($license, $by, $data) {
            $license = License::whereKey($license->id)->lockForUpdate()->firstOrFail();

            if ($reason = $license->assignmentBlockReason()) {
                throw new MovementException($reason);
            }

            $employee = ! empty($data['employee_id']) ? Employee::findOrFail($data['employee_id']) : null;
            $asset = ! empty($data['asset_id']) ? Asset::findOrFail($data['asset_id']) : null;
            $perUser = $license->assignment_type === LicenseAssignmentType::PerUser;

            if ($perUser && $employee === null) {
                throw new MovementException('Licenças por usuário precisam de um colaborador.');
            }

            if (! $perUser && $asset === null) {
                throw new MovementException('Licenças por dispositivo precisam de um equipamento.');
            }

            if ($employee && ! $employee->is_active) {
                throw new MovementException('Não é possível atribuir licenças a um colaborador desligado.');
            }

            if ($asset && $asset->status === AssetStatus::Descartado) {
                throw new MovementException('Não é possível atribuir licenças a um equipamento descartado.');
            }

            // A mesma pessoa (ou o mesmo equipamento) não ocupa duas vagas do mesmo contrato
            $duplicate = $perUser
                ? $license->activeAssignments()->where('employee_id', $employee->id)->exists()
                : $license->activeAssignments()->where('asset_id', $asset->id)->exists();

            if ($duplicate) {
                throw new MovementException($perUser
                    ? 'Este colaborador já usa esta licença.'
                    : 'Este equipamento já usa esta licença.');
            }

            if ($license->activeAssignments()->count() >= $license->seats) {
                throw new MovementException($license->seats === 1
                    ? 'A licença contratada já está em uso. Libere-a ou amplie o contrato.'
                    : "Todas as {$license->seats} licenças contratadas já estão em uso. Libere uma ou amplie o contrato.");
            }

            $assignment = new LicenseAssignment([
                'employee_id' => $employee?->id,
                'asset_id' => $asset?->id,
                'assigned_at' => $data['assigned_at'],
                'notes' => $data['notes'] ?? null,
            ]);
            $assignment->license_id = $license->id;
            $assignment->assigned_by = $by->id;
            $assignment->save();

            return $assignment;
        });
    }

    public function revoke(LicenseAssignment $assignment, User $by, string $revokedAt): void
    {
        if (! $assignment->isActive()) {
            throw new MovementException('Esta atribuição já foi liberada.');
        }

        if (Carbon::parse($revokedAt)->lt($assignment->assigned_at)) {
            throw new MovementException('A data de liberação não pode ser anterior à data de atribuição.');
        }

        $assignment->revoked_at = $revokedAt;
        $assignment->revoked_by = $by->id;
        $assignment->save();
    }
}