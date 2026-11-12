<?php

namespace App\Enums;

enum LocationType: string
{
    case Escritorio = 'escritorio';
    case Obra = 'obra';
    case Almoxarifado = 'almoxarifado';

    public function label(): string
    {
        return match ($this) {
            self::Escritorio => 'Escritório',
            self::Obra => 'Obra',
            self::Almoxarifado => 'Almoxarifado',
        };
    }
}