<?php

declare(strict_types=1);

namespace App\Services\Parsers;

use App\DTOs\ParsedTransaction;
use App\Enums\Bank;

/**
 * Santander Polska — historia operacji (CSV).
 *
 * Typical columns:
 *   0 — Data księgowania (dd-mm-yyyy or dd.mm.yyyy)
 *   1 — Data waluty
 *   2 — Opis nadawcy/odbiorcy
 *   3 — Tytuł
 *   4 — Numer rachunku
 *   5 — Kwota
 *   6 — Saldo po operacji
 */
class SantanderCsvParser extends BaseCsvParser
{
    protected string $delimiter = ',';

    protected string $sourceEncoding = 'Windows-1250';

    protected array $headerSignature = [
        'data księgowania',
        'opis nadawcy/odbiorcy',
    ];

    public function bank(): Bank
    {
        return Bank::Santander;
    }

    protected function mapRow(array $row): ParsedTransaction
    {
        $rawDate = trim((string) ($row[0] ?? ''));
        $format = str_contains($rawDate, '.') ? 'd.m.Y' : 'd-m-Y';
        $postedAt = $this->parseDate($rawDate, $format);

        $counterparty = ($row[2] ?? '') !== '' ? $row[2] : null;
        $description = trim((string) ($row[3] ?? ''));
        $amount = $this->parseAmount($row[5] ?? '0');
        $balance = ($row[6] ?? '') !== '' ? $this->parseAmount($row[6]) : null;

        return new ParsedTransaction(
            postedAt: $postedAt,
            amount: $amount,
            currency: 'PLN',
            description: $description,
            counterparty: $counterparty,
            balance: $balance,
        );
    }
}
