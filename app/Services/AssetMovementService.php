<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\AuditAction;
use App\Exceptions\MovementException;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Regras de entrega e devolução de equipamentos.
 * Toda alteração acontece dentro de uma transação, com o equipamento travado,
 * para impedir duas entregas simultâneas do mesmo item.
 */
class AssetMovementService
{
    public function checkout(Asset $asset, Employee $employee, User $by, array $data): AssetAssignment
    {
        return DB::transaction(function () use ($asset, $employee, $by, $data) {
            $asset = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();

            if ($asset->status !== AssetStatus::Disponivel) {
                throw new MovementException('Este equipamento não está disponível para entrega.');
            }

            if (! $employee->is_active) {
                throw new MovementException('Não é possível entregar equipamentos a um colaborador desligado.');
            }

            // Local de uso: o informado na entrega; senão, o de lotação do colaborador; senão, mantém o atual
            $locationId = $data['location_id'] ?? $employee->location_id ?? $asset->location_id;

            $assignment = new AssetAssignment([
                'employee_id' => $employee->id,
                'location_id' => $locationId,
                'checked_out_at' => $data['checked_out_at'],
                'checkout_condition' => $data['checkout_condition'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $assignment->asset_id = $asset->id;
            $assignment->checked_out_by = $by->id;
            $assignment->term_signed = (bool) ($data['term_signed'] ?? false);
            $assignment->term_signed_at = $assignment->term_signed ? $data['checked_out_at'] : null;
            $assignment->save();

            $asset->status = AssetStatus::EmUso;
            $asset->location_id = $locationId;
            $asset->saveQuietly();

            Audit::record(AuditAction::Checkout, $asset, "Entregou {$asset->auditLabel()} a {$employee->name}", [
                'colaborador' => ['para' => $employee->name],
                'data' => ['para' => $data['checked_out_at']],
                'termo' => ['para' => $assignment->term_signed ? 'assinado' : 'pendente'],
            ]);

            return $assignment;
        });
    }

    public function checkin(Asset $asset, User $by, array $data): AssetAssignment
    {
        return DB::transaction(function () use ($asset, $by, $data) {
            $asset = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $assignment = $asset->currentAssignment()->with('employee')->first();

            if ($asset->status !== AssetStatus::EmUso || $assignment === null) {
                throw new MovementException('Este equipamento não está em uso.');
            }

            if (Carbon::parse($data['checked_in_at'])->lt($assignment->checked_out_at)) {
                throw new MovementException(
                    'A data de devolução não pode ser anterior à data de entrega ('
                    . $assignment->checked_out_at->format('d/m/Y') . ').'
                );
            }

            $assignment->checked_in_at = $data['checked_in_at'];
            $assignment->checked_in_by = $by->id;
            $assignment->checkin_condition = $data['checkin_condition'] ?? null;
            $assignment->save();

            $asset->status = AssetStatus::from($data['status']);
            if (! empty($data['location_id'])) {
                $asset->location_id = $data['location_id'];
            }
            $asset->saveQuietly();

            Audit::record(AuditAction::Checkin, $asset, "Recebeu {$asset->auditLabel()} de {$assignment->employee->name}", [
                'situacao' => ['de' => AssetStatus::EmUso->value, 'para' => $asset->status->value],
                'condicao' => ['para' => $assignment->checkin_condition],
                'data' => ['para' => $data['checked_in_at']],
            ]);

            return $assignment;
        });
    }

    public function signTerm(AssetAssignment $assignment, string $signedAt): void
    {
        if ($assignment->term_signed) {
            throw new MovementException('O termo desta entrega já foi registrado.');
        }

        if (Carbon::parse($signedAt)->lt($assignment->checked_out_at)) {
            throw new MovementException('A data de assinatura não pode ser anterior à data de entrega.');
        }

        $assignment->term_signed = true;
        $assignment->term_signed_at = $signedAt;
        $assignment->save();

        $assignment->loadMissing(['asset', 'employee']);

        Audit::record(
            AuditAction::TermSigned,
            $assignment->asset,
            "Registrou o termo de {$assignment->employee->name} para {$assignment->asset->auditLabel()}",
            ['data' => ['para' => $signedAt]],
        );
    }
}