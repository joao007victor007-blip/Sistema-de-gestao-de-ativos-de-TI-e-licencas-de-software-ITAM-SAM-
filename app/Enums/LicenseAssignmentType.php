<?php

namespace App\Enums;

enum LicenseAssignmentType: string
{
    case PerUser = 'per_user';
    case PerDevice = 'per_device';
    case Corporate = 'corporate';

    public function label(): string
    {
        return match ($this) {
            self::PerUser => 'Por usuário (nominal)',
            self::PerDevice => 'Por dispositivo',
            self::Corporate => 'Corporativa (toda a empresa)',
        };
    }

    /** Licenças corporativas não têm quantidade nem atribuição individual */
    public function usesSeats(): bool
    {
        return $this !== self::Corporate;
    }
}