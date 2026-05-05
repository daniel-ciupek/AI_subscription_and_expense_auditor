<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\ParsedTransaction;
use App\Enums\Bank;

interface CsvParserInterface
{
    public function bank(): Bank;

    /**
     * @param  array<int, string>  $headers  raw header row, already split and trimmed
     */
    public function matchesHeaders(array $headers): bool;

    /**
     * @return iterable<ParsedTransaction>
     */
    public function parse(string $path): iterable;
}
