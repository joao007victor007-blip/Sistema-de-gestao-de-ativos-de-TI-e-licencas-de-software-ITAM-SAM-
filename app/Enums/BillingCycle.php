<?php

namespace App\Enums;

enum BillingCycle: string
{
    case Mensal = 'mensal';
    case Anual = 'anual';
    case Unica = 'unica';

    public function label(): string
    {
        return match ($this) {
            self::Mensal => 'Mensal',
            self::Anual => 'Anual',
            self::Unica => 'Pagamento único',
        };
    }
}