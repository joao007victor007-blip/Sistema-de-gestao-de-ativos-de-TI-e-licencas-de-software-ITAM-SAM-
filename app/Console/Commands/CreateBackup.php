<?php

namespace App\Console\Commands;

use App\Services\BackupManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateBackup extends Command
{
    protected $signature = 'intranet:backup';

    protected $description = 'Gera um backup do banco de dados e remove os backups antigos';

    public function handle(BackupManager $backups): int
    {
        try {
            $path = $backups->create();
        } catch (Throwable $exception) {
            Log::error('Falha ao gerar o backup: ' . $exception->getMessage());
            $this->error('Falha ao gerar o backup. Detalhes no log do sistema.');

            return self::FAILURE;
        }

        $deleted = $backups->prune();
        $message = "Backup gerado: {$path}. Backups antigos removidos: {$deleted}.";

        Log::info($message);
        $this->info($message);

        return self::SUCCESS;
    }
}