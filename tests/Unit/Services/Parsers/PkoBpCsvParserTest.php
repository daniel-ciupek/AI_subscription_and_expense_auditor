<?php

declare(strict_types=1);

use App\Enums\Bank;
use App\Services\Parsers\PkoBpCsvParser;

it('claims PKO BP headers', function () {
    $parser = new PkoBpCsvParser;

    expect($parser->bank())->toBe(Bank::PkoBp);
    expect($parser->matchesHeaders([
        'Data operacji', 'Data waluty', 'Typ transakcji', 'Kwota',
        'Waluta', 'Saldo po operacji', 'Opis transakcji',
    ]))->toBeTrue();
});

it('parses PKO BP rows', function () {
    $parser = new PkoBpCsvParser;
    $rows = iterator_to_array($parser->parse(__DIR__.'/../../../Fixtures/csv/pko_bp.csv'));

    expect($rows)->toHaveCount(2);
    expect($rows[0]->postedAt->toDateString())->toBe('2026-04-15');
    expect($rows[0]->amount)->toBe('-49.99');
    expect($rows[0]->description)->toBe('NETFLIX.COM PAYMENT');
});
