<?php

namespace App\Enums;

enum LicenseType: string
{
    case Assinatura = 'assinatura';
    case Perpetua = 'perpetua';
    case Oem = 'oem';

    public function label(): string
    {
        return match ($this) {
            self::Assinatura => 'Assinatura',
            self::Perpetua => 'Perpétua',
            self::Oem => 'OEM (vinculada ao equipamento)',
        };
    }
}