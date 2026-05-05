<?php

declare(strict_types=1);

use App\Enums\Bank;
use App\Services\Parsers\SantanderCsvParser;

it('claims Santander headers', function () {
    $parser = new SantanderCsvParser;

    expect($parser->bank())->toBe(Bank::Santander);
    expect($parser->matchesHeaders([
        'Data księgowania', 'Data waluty', 'Opis nadawcy/odbiorcy',
        'Tytuł', 'Numer rachunku', 'Kwota', 'Saldo po operacji',
    ]))->toBeTrue();
});

it('parses Santander rows with dash date format', function () {
    $parser = new SantanderCsvParser;
    $rows = iterator_to_array($parser->parse(__DIR__.'/../../../Fixtures/csv/santander.csv'));

    expect($rows)->toHaveCount(2);
    expect($rows[0]->postedAt->toDateString())->toBe('2026-04-15');
    expect($rows[0]->amount)->toBe('-49.99');
});
