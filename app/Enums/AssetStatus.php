<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Disponivel = 'disponivel';
    case EmUso = 'em_uso';
    case Manutencao = 'manutencao';
    case Extraviado = 'extraviado';
    case Descartado = 'descartado';

    public function label(): string
    {
        return match ($this) {
            self::Disponivel => 'Disponível',
            self::EmUso => 'Em uso',
            self::Manutencao => 'Em manutenção',
            self::Extraviado => 'Extraviado',
            self::Descartado => 'Descartado',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Disponivel => 'bg-green-100 text-green-800',
            self::EmUso => 'bg-blue-100 text-blue-800',
            self::Manutencao => 'bg-yellow-100 text-yellow-800',
            self::Extraviado => 'bg-red-100 text-red-800',
            self::Descartado => 'bg-gray-200 text-gray-700',
        };
    }

    /** Situações que podem ser escolhidas manualmente no formulário */
    public static function editable(): array
    {
        return [self::Disponivel, self::Manutencao, self::Extraviado];
    }

    public function isEditable(): bool
    {
        return in_array($this, self::editable(), true);
    }
}