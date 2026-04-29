<?php

declare(strict_types=1);

namespace App\Services\Parsers;

use App\Contracts\CsvParserInterface;
use App\DTOs\ParsedTransaction;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

abstract class BaseCsvParser implements CsvParserInterface
{
    /** @var non-empty-string */
    protected string $delimiter = ';';

    /** @var non-empty-string */
    protected string $sourceEncoding = 'UTF-8';

    protected int $dataStartRowIndex = 1;

    /**
     * Required header substrings (case-insensitive). All must be present
     * for the parser to claim the file.
     *
     * @var array<int, string>
     */
    protected array $headerSignature = [];

    public function matchesHeaders(array $headers): bool
    {
        if ($this->headerSignature === []) {
            return false;
        }

        $joined = mb_strtolower(implode('|', $headers));

        foreach ($this->headerSignature as $needle) {
            if (! str_contains($joined, mb_strtolower($needle))) {
                return false;
            }
        }

        return true;
    }

    public function parse(string $path): iterable
    {
        $reader = new StatementReader(
            delimiter: $this->delimiter,
            sourceEncoding: $this->sourceEncoding,
        );

        $rowIndex = 0;
        foreach ($reader->rows($path) as $row) {
            if ($rowIndex < $this->dataStartRowIndex) {
                $rowIndex++;

                continue;
            }

            if ($this->isEmptyRow($row)) {
                $rowIndex++;

                continue;
            }

            $parsed = $this->mapRow($row);
            if ($parsed !== null) {
                yield $parsed;
            }

            $rowIndex++;
        }
    }

    /**
     * Map a normalized data row to a ParsedTransaction.
     * Return null to skip the row (e.g. pending/rejected transactions).
     *
     * @param  array<int, string>  $row
     */
    abstract protected function mapRow(array $row): ?ParsedTransaction;

    /**
     * @param  array<int, string>  $row
     */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== '') {
                return false;
            }
        }

        return true;
    }

    protected function parseAmount(string $value): string
    {
        $value = trim($value);
        // Strip every whitespace flavor (regular space, NBSP, thin space, etc.)
        $value = preg_replace('/\s+/u', '', $value) ?? $value;
        // Polish decimal comma → dot.
        $value = str_replace(',', '.', $value);

        if ($value === '' || ! is_numeric($value)) {
            throw new RuntimeException("Invalid amount: {$value}");
        }

        return (string) round((float) $value, 2);
    }

    protected function parseDate(string $value, string $format): CarbonImmutable
    {
        $trimmed = trim($value);

        // Spreadsheet exports occasionally hand us Excel date serials (e.g. 46141)
        // when the source cell is formatted as "General" instead of "Date".
        // 25569 is 1970-01-01; threshold is generous enough to accept any year
        // a transaction could plausibly carry, while rejecting bare amounts.
        if (preg_match('/^\d+(\.\d+)?$/', $trimmed) === 1) {
            $serial = (float) $trimmed;
            if ($serial >= 25569 && $serial <= 80000) {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject($serial))->startOfDay();
            }
        }

        $date = CarbonImmutable::createFromFormat($format, $trimmed);
        if (! $date instanceof CarbonImmutable) {
            throw new RuntimeException("Invalid date '{$value}' for format '{$format}'");
        }

        return $date->startOfDay();
    }
}
