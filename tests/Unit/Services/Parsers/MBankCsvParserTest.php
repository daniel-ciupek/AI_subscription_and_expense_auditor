<?php

declare(strict_types=1);

use App\Enums\Bank;
use App\Services\Parsers\MBankCsvParser;

it('claims mBank headers', function () {
    $parser = new MBankCsvParser;

    expect($parser->bank())->toBe(Bank::MBank);
    expect($parser->matchesHeaders([
        '#Data operacji', '#Data księgowania', '#Opis operacji', '#Tytuł',
        '#Nadawca/Odbiorca', '#Numer konta', '#Kwota', '#Saldo po operacji',
    ]))->toBeTrue();
});

it('parses mBank rows', function () {
    $parser = new MBankCsvParser;
    $rows = iterator_to_array($parser->parse(__DIR__.'/../../../Fixtures/csv/mbank.csv'));

    expect($rows)->toHaveCount(2);
    expect($rows[0]->postedAt->toDateString())->toBe('2026-04-15');
    expect($rows[0]->amount)->toBe('-49.99');
    expect($rows[0]->balance)->toBe('1234.56');
    expect($rows[0]->description)->toContain('NETFLIX');
});
