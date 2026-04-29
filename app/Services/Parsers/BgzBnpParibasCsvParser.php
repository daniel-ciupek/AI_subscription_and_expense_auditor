<?php

declare(strict_types=1);

namespace App\Services\Parsers;

use App\DTOs\ParsedTransaction;
use App\Enums\Bank;

/**
 * BNP Paribas Polska (formerly BGŻ BNP Paribas) — "Pełna lista operacji" CSV.
 *
 * Default columns observed in exports from GOonline (web banking):
 *   0 — Data księgowania (booking date, dd.mm.yyyy)
 *   1 — Data transakcji
 *   2 — Opis
 *   3 — Kontrahent / nadawca / odbiorca
 *   4 — Numer rachunku kontrahenta
 *   5 — Kwota operacji
 *   6 — Waluta
 *   7 — Saldo po operacji
 *
 * Verify against a real export — column order can drift between portal versions.
 */
class BgzBnpParibasCsvParser extends BaseCsvParser
{
    protected string $delimiter = ';';

    protected string $sourceEncoding = 'Windows-1250';

    protected array $headerSignature = [
        'data księgowania',
        'numer rachunku kontrahenta',
    ];

    public function bank(): Bank
    {
        return Bank::BgzBnpParibas;
    }

    protected function mapRow(array $row): ParsedTransaction
    {
        $postedAt = $this->parseDate($row[0] ?? '', 'd.m.Y');
        $description = $row[2] ?? '';
        $counterparty = ($row[3] ?? '') !== '' ? $row[3] : null;
        $amount = $this->parseAmount($row[5] ?? '0');
        $currency = ($row[6] ?? '') !== '' ? mb_strtoupper($row[6]) : 'PLN';
        $balance = ($row[7] ?? '') !== '' ? $this->parseAmount($row[7]) : null;

        return new ParsedTransaction(
            postedAt: $postedAt,
            amount: $amount,
            currency: $currency,
            description: $description,
            counterparty: $counterparty,
            balance: $balance,
        );
    }
}
