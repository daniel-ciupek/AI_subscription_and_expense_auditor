<?php

declare(strict_types=1);

namespace App\Services\Parsers;

use App\Contracts\CsvParserInterface;
use App\DTOs\ParsedTransaction;
use Carbon\CarbonImmutable;
use RuntimeException;

abstract class BaseCsvParser implements CsvParserInterface
{
    /** @var non-empty-string */
    protected string $delimiter = ';';

    /** @var non-empty-string */
    protected string $sourceEncoding = 'UTF-8';

    protected int $headerRowIndex = 0;

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
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot open CSV file: {$path}");
        }

        try {
            $rowIndex = 0;
            while (($row = fgetcsv($handle, 0, $this->delimiter, '"', '\\')) !== false) {
                if ($rowIndex < $this->dataStartRowIndex) {
                    $rowIndex++;

                    continue;
                }

                $row = $this->normalizeRow($row);
                if ($this->isEmptyRow($row)) {
                    $rowIndex++;

                    continue;
                }

                yield $this->mapRow($row);
                $rowIndex++;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<int, string>  $row
     */
    abstract protected function mapRow(array $row): ParsedTransaction;

    /**
     * @param  array<int, string|null>  $row
     * @return array<int, string>
     */
    protected function normalizeRow(array $row): array
    {
        return array_map(
            fn (?string $cell): string => trim($this->toUtf8($cell ?? '')),
            $row,
        );
    }

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

    protected function toUtf8(string $value): string
    {
        $value = ltrim($value, "\xEF\xBB\xBF");

        // If the bytes are already valid UTF-8 we are done; this lets callers
        // declare a "preferred" source encoding while still accepting UTF-8
        // exports that are increasingly common in modern bank portals.
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = mb_convert_encoding($value, 'UTF-8', $this->sourceEncoding);

        return is_string($converted) ? $converted : $value;
    }

    protected function parseAmount(string $value): string
    {
        $value = trim($value);
        $value = str_replace(["\u{00A0}", ' '], '', $value);
        // Normalize Polish decimal comma to dot.
        $value = str_replace(',', '.', $value);

        if ($value === '' || ! is_numeric($value)) {
            throw new RuntimeException("Invalid amount: {$value}");
        }

        return (string) round((float) $value, 2);
    }

    protected function parseDate(string $value, string $format): CarbonImmutable
    {
        $date = CarbonImmutable::createFromFormat($format, trim($value));
        if (! $date instanceof CarbonImmutable) {
            throw new RuntimeException("Invalid date '{$value}' for format '{$format}'");
        }

        return $date->startOfDay();
    }
}
