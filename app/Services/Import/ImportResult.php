<?php

namespace App\Services\Import;

/**
 * Erros e contagens de uma importação (prévia ou definitiva).
 */
class ImportResult
{
    private const LABELS = [
        'locais' => 'Locais',
        'colaboradores' => 'Colaboradores',
        'ativos' => 'Ativos',
        'entregas' => 'Entregas registradas',
        'licencas' => 'Licenças',
        'atribuicoes' => 'Atribuições de licença',
        'categorias' => 'Categorias',
        'fabricantes' => 'Fabricantes',
        'setores' => 'Setores',
        'softwares' => 'Softwares',
    ];

    private array $errors = [];
    private array $created = [];
    private array $reused = [];

    public function error(string $sheet, ?int $line, string $message): void
    {
        $this->errors[] = ['sheet' => $sheet, 'line' => $line, 'message' => $message];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function created(string $key): void
    {
        $this->created[$key] = ($this->created[$key] ?? 0) + 1;
    }

    public function reused(string $key): void
    {
        $this->reused[$key] = ($this->reused[$key] ?? 0) + 1;
    }

    /** Linhas do resumo: [rótulo, novos, já existentes] */
    public function summary(): array
    {
        $rows = [];

        foreach (self::LABELS as $key => $label) {
            $created = $this->created[$key] ?? 0;
            $reused = $this->reused[$key] ?? 0;

            if ($created > 0 || $reused > 0) {
                $rows[] = ['label' => $label, 'created' => $created, 'reused' => $reused];
            }
        }

        return $rows;
    }

    public function auditSummary(): array
    {
        return collect($this->created)
            ->mapWithKeys(fn (int $count, string $key) => [$key => ['para' => $count]])
            ->all();
    }
}