<?php

declare(strict_types=1);

namespace App\Services\Parsers;

use App\DTOs\ParsedTransaction;
use App\Enums\Bank;

/**
 * PKO BP — "Historia rachunku" CSV.
 *
 * Typical columns:
 *   0 — Data operacji (yyyy-mm-dd)
 *   1 — Data waluty
 *   2 — Typ transakcji
 *   3 — Kwota
 *   4 — Waluta
 *   5 — Saldo po operacji
 *   6 — Opis transakcji
 *   7 — (additional opisowe pola)
 */
class PkoBpCsvParser extends BaseCsvParser
{
    protected string $delimiter = ',';

    protected string $sourceEncoding = 'Windows-1250';

    protected array $headerSignature = [
        'typ transakcji',
        'opis transakcji',
    ];

    public function bank(): Bank
    {
        return Bank::PkoBp;
    }

    protected function mapRow(array $row): ParsedTransaction
    {
        $postedAt = $this->parseDate($row[0] ?? '', 'Y-m-d');
        $amount = $this->parseAmount($row[3] ?? '0');
        $currency = ($row[4] ?? '') !== '' ? mb_strtoupper($row[4]) : 'PLN';
        $balance = ($row[5] ?? '') !== '' ? $this->parseAmount($row[5]) : null;
        $description = trim((string) ($row[6] ?? ''));

        return new ParsedTransaction(
            postedAt: $postedAt,
            amount: $amount,
            currency: $currency,
            description: $description,
            counterparty: null,
            balance: $balance,
        );
    }
}
