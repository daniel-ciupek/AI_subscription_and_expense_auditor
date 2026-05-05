<?php

declare(strict_types=1);

namespace App\Services\Parsers;

use App\DTOs\ParsedTransaction;
use App\Enums\Bank;

/**
 * ING — "Historia operacji" CSV.
 *
 * Typical columns (UTF-8, semicolon-separated):
 *   0 — Data transakcji (yyyy-mm-dd)
 *   1 — Data księgowania
 *   2 — Dane kontrahenta
 *   3 — Tytuł operacji
 *   4 — Numer konta
 *   5 — Kwota
 *   6 — Waluta
 *   7 — Saldo po operacji
 */
class IngCsvParser extends BaseCsvParser
{
    protected string $delimiter = ';';

    protected string $sourceEncoding = 'UTF-8';

    protected array $headerSignature = [
        'data transakcji',
        'tytuł operacji',
        'dane kontrahenta',
    ];

    public function bank(): Bank
    {
        return Bank::Ing;
    }

    protected function mapRow(array $row): ParsedTransaction
    {
        $postedAt = $this->parseDate($row[0] ?? '', 'Y-m-d');
        $counterparty = ($row[2] ?? '') !== '' ? $row[2] : null;
        $description = trim((string) ($row[3] ?? ''));
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
