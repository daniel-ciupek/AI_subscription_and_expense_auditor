<?php

declare(strict_types=1);

namespace App\Services\Parsers;

use Generator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use RuntimeException;

/**
 * Reads tabular bank statements from CSV, XLS or XLSX files into a uniform
 * stream of string-array rows. Cell values are coerced to strings so the
 * downstream parser can treat all sources identically.
 */
class StatementReader
{
    public function __construct(
        private string $delimiter = ';',
        private string $sourceEncoding = 'UTF-8',
    ) {}

    /**
     * @return Generator<int, array<int, string>>
     */
    public function rows(string $path): Generator
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['xls', 'xlsx', 'ods'], true)) {
            yield from $this->readSpreadsheet($path);

            return;
        }

        yield from $this->readCsv($path);
    }

    /**
     * Read just the first non-empty row — used for header-based bank detection.
     *
     * @return array<int, string>|null
     */
    public function firstRow(string $path): ?array
    {
        foreach ($this->rows($path) as $row) {
            foreach ($row as $cell) {
                if ($cell !== '') {
                    return $row;
                }
            }
        }

        return null;
    }

    /**
     * @return Generator<int, array<int, string>>
     */
    private function readCsv(string $path): Generator
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot open file: {$path}");
        }

        try {
            while (($row = fgetcsv($handle, 0, $this->delimiter, '"', '\\')) !== false) {
                yield array_map(
                    fn ($cell): string => $this->normalizeCell((string) ($cell ?? '')),
                    $row,
                );
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return Generator<int, array<int, string>>
     */
    private function readSpreadsheet(string $path): Generator
    {
        $reader = $this->buildSpreadsheetReader($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($sheet->getRowIterator() as $row) {
            $cells = $row->getCellIterator();
            $cells->setIterateOnlyExistingCells(false);

            $values = [];
            foreach ($cells as $cell) {
                $values[] = $this->normalizeCell((string) $cell->getFormattedValue());
            }

            yield $values;
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function buildSpreadsheetReader(string $path): IReader
    {
        try {
            return IOFactory::createReaderForFile($path);
        } catch (\Throwable $e) {
            throw new RuntimeException("Cannot determine spreadsheet format for: {$path}", 0, $e);
        }
    }

    private function normalizeCell(string $value): string
    {
        $value = ltrim($value, "\xEF\xBB\xBF");
        $value = trim($value);

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = mb_convert_encoding($value, 'UTF-8', $this->sourceEncoding);

        return is_string($converted) ? $converted : $value;
    }
}
