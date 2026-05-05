<?php

declare(strict_types=1);

use App\Enums\Bank;
use App\Services\Parsers\IngCsvParser;

it('claims ING headers', function () {
    $parser = new IngCsvParser;

    expect($parser->bank())->toBe(Bank::Ing);
    expect($parser->matchesHeaders([
        'Data transakcji', 'Data księgowania', 'Dane kontrahenta',
        'Tytuł operacji', 'Numer konta', 'Kwota', 'Waluta', 'Saldo po operacji',
    ]))->toBeTrue();
});

it('parses ING rows', function () {
    $parser = new IngCsvParser;
    $rows = iterator_to_array($parser->parse(__DIR__.'/../../../Fixtures/csv/ing.csv'));

    expect($rows)->toHaveCount(2);
    expect($rows[0]->postedAt->toDateString())->toBe('2026-04-15');
    expect($rows[0]->description)->toBe('NETFLIX SUBSCRIPTION');
    expect($rows[0]->counterparty)->toBe('NETFLIX INT BV');
});
