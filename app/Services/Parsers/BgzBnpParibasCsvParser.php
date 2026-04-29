<?php

declare(strict_types=1);

namespace App\Services\Parsers;

use App\DTOs\ParsedTransaction;
use App\Enums\Bank;

/**
 * BNP Paribas Polska (formerly BGŻ BNP Paribas) — "Historia operacji" export
 * from GOonline. The portal currently offers PDF and XLS/XLSX downloads.
 *
 * Observed column order (verified against a real XLS export):
 *   0  Data transakcji (yyyy-mm-dd)
 *   1  Data zaksięgowania (yyyy-mm-dd, empty for pending rows)
 *   2  Data odrzucenia (filled only for rejected transfers)
 *   3  Kwota (e.g. "- 50,17", spaces and non-breaking spaces in groups)
 *   4  Waluta
 *   5  Nadawca
 *   6  Odbiorca
 *   7  Opis
 *   8  Produkt (account name + IBAN)
 *   9  Typ transakcji (Transakcja kartą / Blokada środków / Przelew …)
 *   10 Kwota zlecenia
 *   11 Waluta zlecenia
 *   12 Status (Zrealizowana / W trakcie realizacji / Odrzucona)
 *   13 Saldo po transakcji (empty for pending rows)
 *
 * Pending and rejected rows are skipped — they do not represent settled
 * activity and would either change or vanish on the next export.
 */
class BgzBnpParibasCsvParser extends BaseCsvParser
{
    protected string $delimiter = ';';

    protected string $sourceEncoding = 'UTF-8';

    protected array $headerSignature = [
        'data zaksięgowania',
        'odbiorca',
        'typ transakcji',
    ];

    public function bank(): Bank
    {
        return Bank::BgzBnpParibas;
    }

    protected function mapRow(array $row): ?ParsedTransaction
    {
        $status = mb_strtolower((string) ($row[12] ?? ''));
        if ($status === '' || str_contains($status, 'w trakcie') || str_contains($status, 'odrzucon')) {
            return null;
        }

        // Booking date is the canonical posted_at; fall back to transaction date
        // when the export omits it (rare for settled rows but cheap to handle).
        $postedRaw = ($row[1] ?? '') !== '' ? $row[1] : ($row[0] ?? '');
        $postedAt = $this->parseDate($postedRaw, 'Y-m-d');

        $amount = $this->parseAmount($row[3] ?? '0');
        $currency = ($row[4] ?? '') !== '' ? mb_strtoupper($row[4]) : 'PLN';

        $sender = $row[5] ?? '';
        $recipient = $row[6] ?? '';
        $opis = $row[7] ?? '';
        $type = $row[9] ?? '';

        $description = $opis !== '' ? $opis : trim($type.' '.$recipient);
        if ($description === '') {
            $description = '(no description)';
        }

        $counterparty = $recipient !== '' ? $recipient : ($sender !== '' ? $sender : null);

        $balance = ($row[13] ?? '') !== '' ? $this->parseAmount($row[13]) : null;

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
