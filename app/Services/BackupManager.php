<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
use ZipArchive;

/**
 * Gera, lista e remove backups do banco de dados.
 * Os arquivos ficam em storage/app/private/backups, fora do alcance da web.
 */
class BackupManager
{
    public const DIRECTORY = 'backups';
    public const KEEP_DAYS = 14;
    public const NAME_PATTERN = '/^backup-\d{4}-\d{2}-\d{2}-\d{6}\.sql(\.gz)?$/';

    /** Tabelas temporárias: entram só com a estrutura, sem o conteúdo */
    private const SKIP_DATA = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    private const ROWS_PER_INSERT = 200;

    /** Gera um backup completo e devolve o caminho relativo do arquivo */
    public function create(): string
    {
        $compressed = function_exists('gzopen');
        $name = 'backup-' . now()->format('Y-m-d-His') . ($compressed ? '.sql.gz' : '.sql');
        $relative = self::DIRECTORY . '/' . $name;

        $disk = Storage::disk('local');
        $disk->makeDirectory(self::DIRECTORY);
        $path = $disk->path($relative);

        $handle = $compressed ? gzopen($path, 'wb6') : fopen($path, 'wb');
        $write = fn (string $text) => $compressed ? gzwrite($handle, $text) : fwrite($handle, $text);

        try {
            $this->dump($write);
        } catch (Throwable $exception) {
            $compressed ? gzclose($handle) : fclose($handle);
            $disk->delete($relative);

            throw $exception;
        }

        $compressed ? gzclose($handle) : fclose($handle);

        return $relative;
    }

    /** Apaga os backups mais antigos que o prazo de retenção; devolve quantos apagou */
    public function prune(): int
    {
        $disk = Storage::disk('local');
        $limit = now()->subDays(self::KEEP_DAYS)->getTimestamp();
        $deleted = 0;

        foreach ($disk->files(self::DIRECTORY) as $file) {
            if (preg_match(self::NAME_PATTERN, basename($file)) && $disk->lastModified($file) < $limit) {
                $disk->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /** Backups existentes, do mais recente ao mais antigo */
    public function list(): Collection
    {
        $disk = Storage::disk('local');

        return collect($disk->files(self::DIRECTORY))
            ->filter(fn (string $file) => preg_match(self::NAME_PATTERN, basename($file)))
            ->map(fn (string $file) => [
                'name' => basename($file),
                'size' => $disk->size($file),
                'created_at' => Carbon::createFromTimestamp($disk->lastModified($file), config('app.timezone')),
            ])
            ->sortByDesc('created_at')
            ->values();
    }

    public function latest(): ?array
    {
        return $this->list()->first();
    }

    /** Caminho completo de um backup, ou null se o nome for inválido ou o arquivo não existir */
    public function path(string $name): ?string
    {
        if (! preg_match(self::NAME_PATTERN, $name)) {
            return null;
        }

        $relative = self::DIRECTORY . '/' . $name;

        return Storage::disk('local')->exists($relative) ? Storage::disk('local')->path($relative) : null;
    }

        /** Espaço total ocupado pelos anexos, em bytes */
    public function attachmentsSize(): int
    {
        $disk = Storage::disk('local');

        return (int) array_sum(array_map(fn (string $file) => $disk->size($file), $disk->allFiles('attachments')));
    }

    /** Gera um .zip temporário com todos os anexos; devolve o caminho, ou null se não houver anexos */
    public function attachmentsZip(): ?string
    {
        $disk = Storage::disk('local');
        $files = $disk->allFiles('attachments');

        if ($files === []) {
            return null;
        }

        $disk->makeDirectory('tmp');
        $zipPath = $disk->path('tmp/anexos-' . now()->format('Y-m-d-His') . '.zip');

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $file) {
            $zip->addFile($disk->path($file), $file);
        }

        $zip->close();

        return $zipPath;
    }

    private function dump(callable $write): void
    {
        $database = DB::connection()->getDatabaseName();

        $write("-- Backup da Intranet de TI\n");
        $write("-- Banco de origem: {$database}\n");
        $write('-- Gerado em: ' . now()->format('d/m/Y H:i:s') . "\n\n");
        $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = collect(DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
            ->map(fn ($row) => array_values((array) $row)[0]);

        foreach ($tables as $table) {
            $create = (array) DB::selectOne("SHOW CREATE TABLE `{$table}`");

            $write("DROP TABLE IF EXISTS `{$table}`;\n");
            $write($create['Create Table'] . ";\n\n");

            if (! in_array($table, self::SKIP_DATA, true)) {
                $this->dumpTableData($table, $write);
            }
        }

        $write("SET FOREIGN_KEY_CHECKS=1;\n");
    }

    private function dumpTableData(string $table, callable $write): void
    {
        $pdo = DB::connection()->getPdo();
        $columns = null;
        $batch = [];

        $flush = function () use (&$batch, &$columns, $table, $write) {
            if ($batch === []) {
                return;
            }

            $write("INSERT INTO `{$table}` ({$columns}) VALUES\n" . implode(",\n", $batch) . ";\n");
            $batch = [];
        };

        foreach (DB::table($table)->cursor() as $row) {
            $row = (array) $row;
            $columns ??= '`' . implode('`, `', array_keys($row)) . '`';

            $values = array_map(fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), $row);
            $batch[] = '(' . implode(', ', $values) . ')';

            if (count($batch) === self::ROWS_PER_INSERT) {
                $flush();
            }
        }

        $flush();
        $write("\n");
    }
}