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
     * Detect bank by reading the first non-empty row of the CSV file.
     */
    public function detect(string $path): ?CsvParserInterface
    {
        $headers = $this->readHeaderRow($path);
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
     * @return array<int, string>|null
     */
    private function readHeaderRow(string $path): ?array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            // Try common delimiters until we find one that splits into >1 column.
            $sample = (string) fread($handle, 4096);
            rewind($handle);

            $delimiter = $this->guessDelimiter($sample);

            while (($row = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
                $row = array_map(
                    fn ($cell): string => trim($this->stripBom((string) ($cell ?? ''))),
                    $row,
                );

                $hasContent = false;
                foreach ($row as $cell) {
                    if ($cell !== '') {
                        $hasContent = true;
                        break;
                    }
                }

                if ($hasContent) {
                    return $row;
                }
            }
        } finally {
            fclose($handle);
        }

        return null;
    }

    private function guessDelimiter(string $sample): string
    {
        $candidates = [';', ',', "\t"];
        $best = ',';
        $bestCount = -1;

        foreach ($candidates as $candidate) {
            $count = substr_count($sample, $candidate);
            if ($count > $bestCount) {
                $bestCount = $count;
                $best = $candidate;
            }
        }

        return $best;
    }

    private function stripBom(string $value): string
    {
        return ltrim($value, "\xEF\xBB\xBF");
    }
}
