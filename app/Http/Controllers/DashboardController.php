<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\LicenseAssignmentType;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Category;
use App\Models\Employee;
use App\Models\License;
use App\Services\BackupManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(BackupManager $backups): View
    {
        // Quantidade de ativos por situação, numa única consulta
        $statusCounts = Asset::query()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $count = fn (AssetStatus $status): int => (int) ($statusCounts[$status->value] ?? 0);

        $licensesNearLimit = License::notRetired()
            ->where('assignment_type', '!=', LicenseAssignmentType::Corporate->value)
            ->withCount('activeAssignments')
            ->with('software')
            ->get()
            ->filter(fn (License $license) => $license->seats > 0
                && $license->active_assignments_count / $license->seats >= 0.9)
            ->sortByDesc(fn (License $license) => $license->active_assignments_count / $license->seats)
            ->values();

        // Só administradores recebem o alerta de backup atrasado
        $backupIsLate = false;
        if (Gate::allows('manage-backups')) {
            $latest = $backups->latest();
            $backupIsLate = ! $latest || $latest['created_at']->lt(now()->subHours(48));
        }

        return view('dashboard', [
            'backupIsLate' => $backupIsLate,
            'assets' => [
                'total' => (int) $statusCounts->sum() - $count(AssetStatus::Descartado),
                'emUso' => $count(AssetStatus::EmUso),
                'disponiveis' => $count(AssetStatus::Disponivel),
                'manutencao' => $count(AssetStatus::Manutencao),
                'extraviados' => $count(AssetStatus::Extraviado),
            ],
            'expiredLicensesCount' => License::expired()->count(),
            'expiringLicenses' => License::expiringSoon()->with('software')->orderBy('expires_at')->get(),
            'upcomingRenewals' => License::notRetired()
                ->autoRenewing()
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', today()->addDays(30)->toDateString())
                ->with('software')
                ->orderBy('expires_at')
                ->get(),

            'pendingTerms' => AssetAssignment::query()
                ->whereNull('checked_in_at')
                ->where('term_signed', false)
                ->with(['asset.category', 'asset.manufacturer', 'employee'])
                ->orderBy('checked_out_at')
                ->get(),
            'terminatedWithAssets' => Employee::query()
                ->where('is_active', false)
                ->whereHas('openAssetAssignments')
                ->withCount('openAssetAssignments')
                ->orderBy('terminated_at')
                ->get(),
            'licensesNearLimit' => $licensesNearLimit,
            'recentMovements' => AssetAssignment::query()
                ->with(['asset.category', 'asset.manufacturer', 'employee'])
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->limit(8)
                ->get(),
            'assetsByCategory' => Category::query()
                ->withCount(['assets' => fn ($query) => $query->where('status', '!=', AssetStatus::Descartado->value)])
                ->get()
                ->where('assets_count', '>', 0)
                ->sortByDesc('assets_count')
                ->values(),
        ]);
    }
}