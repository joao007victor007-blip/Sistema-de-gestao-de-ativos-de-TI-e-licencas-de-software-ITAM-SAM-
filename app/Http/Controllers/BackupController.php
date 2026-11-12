<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Services\BackupManager;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(private BackupManager $backups)
    {
    }

        public function index(): View
    {
        Gate::authorize('manage-backups');

        $files = $this->backups->list();

        return view('backups.index', [
            'files' => $files,
            'latest' => $files->first(),
            'keepDays' => BackupManager::KEEP_DAYS,
            'attachmentsSize' => $this->backups->attachmentsSize(),
        ]);
    }

    public function store(): RedirectResponse
    {
        Gate::authorize('manage-backups');

        try {
            $path = $this->backups->create();
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', 'Não foi possível gerar o backup. O erro foi registrado no log do sistema.');
        }

        $this->backups->prune();

        Audit::record(AuditAction::BackupCreated, null, 'Gerou um backup manual: ' . basename($path));

        return back()->with('status', 'Backup gerado: ' . basename($path));
    }

    public function download(string $name): BinaryFileResponse
    {
        Gate::authorize('manage-backups');

        $path = $this->backups->path($name);

        abort_if($path === null, 404);

        Audit::record(AuditAction::BackupDownloaded, null, "Baixou o backup {$name}");

        return response()->download($path);
    }

        public function downloadAttachments(): BinaryFileResponse|RedirectResponse
    {
        Gate::authorize('manage-backups');

        $zipPath = $this->backups->attachmentsZip();

        if ($zipPath === null) {
            return back()->with('error', 'Ainda não há anexos para baixar.');
        }

        Audit::record(AuditAction::BackupDownloaded, null, 'Baixou o pacote com todos os anexos');

        return response()->download($zipPath, basename($zipPath))->deleteFileAfterSend(true);
    }

}