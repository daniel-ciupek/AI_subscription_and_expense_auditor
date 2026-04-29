<?php

declare(strict_types=1);

namespace App\Enums;

enum Bank: string
{
    case MBank = 'mbank';
    case PkoBp = 'pko_bp';
    case Ing = 'ing';
    case Santander = 'santander';
    case BgzBnpParibas = 'bgz_bnp_paribas';

    public function label(): string
    {
        return match ($this) {
            self::MBank => 'mBank',
            self::PkoBp => 'PKO BP',
            self::Ing => 'ING',
            self::Santander => 'Santander',
            self::BgzBnpParibas => 'BGŻ BNP Paribas',
        };
    }
}
