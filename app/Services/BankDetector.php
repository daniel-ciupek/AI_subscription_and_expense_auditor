<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CsvParserInterface;
use App\Enums\Bank;
use App\Services\Parsers\BgzBnpParibasCsvParser;
use App\Services\Parsers\IngCsvParser;
use App\Services\Parsers\MBankCsvParser;
use App\Services\Parsers\PkoBpCsvParser;
use App\Services\Parsers\SantanderCsvParser;
use App\Services\Parsers\StatementReader;
use RuntimeException;

class BankDetector
{
    /**
     * @var array<int, CsvParserInterface>
     */
    private array $parsers;

    public function __construct()
    {
        $this->parsers = [
            new BgzBnpParibasCsvParser,
            new MBankCsvParser,
            new PkoBpCsvParser,
            new IngCsvParser,
            new SantanderCsvParser,
        ];
    }

    public function parserFor(Bank $bank): CsvParserInterface
    {
        foreach ($this->parsers as $parser) {
            if ($parser->bank() === $bank) {
                return $parser;
            }
        }

        throw new RuntimeException("No parser registered for bank: {$bank->value}");
    }

    /**
     * Detect bank by reading the first non-empty row of a CSV/XLS/XLSX file.
     */
    public function detect(string $path): ?CsvParserInterface
    {
        $reader = new StatementReader(
            delimiter: $this->guessCsvDelimiter($path),
        );

        $headers = $reader->firstRow($path);
        if ($headers === null) {
            return null;
        }

        foreach ($this->parsers as $parser) {
            if ($parser->matchesHeaders($headers)) {
                return $parser;
            }
        }

        return null;
    }

    /**
     * Sniff a likely CSV delimiter from the first chunk of the file. Ignored
     * for spreadsheets — StatementReader uses PhpSpreadsheet there.
     */
    private function guessCsvDelimiter(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, ['xls', 'xlsx', 'ods'], true)) {
            return ';';
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return ',';
        }

        $sample = (string) fread($handle, 4096);
        fclose($handle);

        $best = ',';
        $bestCount = -1;
        foreach ([';', ',', "\t"] as $candidate) {
            $count = substr_count($sample, $candidate);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $candidate;
            }
        }

        return $best;
    }
}
