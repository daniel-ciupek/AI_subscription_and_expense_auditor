<?php

declare(strict_types=1);

namespace App\Services\Parsers;

use App\DTOs\ParsedTransaction;
use App\Enums\Bank;

/**
 * mBank — "Historia operacji" CSV export.
 *
 * Typical structure (after the prelude rows that we skip via dataStartRowIndex):
 *   0 — #Data operacji (yyyy-mm-dd)
 *   1 — #Data księgowania
 *   2 — #Opis operacji
 *   3 — #Tytuł
 *   4 — #Nadawca/Odbiorca
 *   5 — #Numer konta
 *   6 — #Kwota
 *   7 — #Saldo po operacji
 */
class MBankCsvParser extends BaseCsvParser
{
    protected string $delimiter = ';';

    protected string $sourceEncoding = 'Windows-1250';

    protected array $headerSignature = [
        '#data operacji',
        '#opis operacji',
    ];

    public function bank(): Bank
    {
        return Bank::MBank;
    }

    protected function mapRow(array $row): ParsedTransaction
    {
        $postedAt = $this->parseDate($row[0] ?? '', 'Y-m-d');
        $description = trim(($row[2] ?? '').' '.($row[3] ?? ''));
        $counterparty = ($row[4] ?? '') !== '' ? $row[4] : null;
        $amount = $this->parseAmount($row[6] ?? '0');
        $balance = ($row[7] ?? '') !== '' ? $this->parseAmount($row[7]) : null;

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
