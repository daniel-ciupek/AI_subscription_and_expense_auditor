<?php

declare(strict_types=1);

use App\DTOs\ParsedTransaction;
use App\Enums\Bank;
use App\Services\Parsers\BgzBnpParibasCsvParser;

it('claims BGŻ BNP Paribas headers', function () {
    $parser = new BgzBnpParibasCsvParser;

    expect($parser->bank())->toBe(Bank::BgzBnpParibas);
    expect($parser->matchesHeaders([
        'Data księgowania', 'Data transakcji', 'Opis', 'Kontrahent',
        'Numer rachunku kontrahenta', 'Kwota', 'Waluta', 'Saldo po operacji',
    ]))->toBeTrue();
});

it('rejects unrelated headers', function () {
    $parser = new BgzBnpParibasCsvParser;

    expect($parser->matchesHeaders(['Date', 'Description', 'Amount']))->toBeFalse();
});

it('parses BGŻ rows into ParsedTransaction objects', function () {
    $parser = new BgzBnpParibasCsvParser;
    $rows = iterator_to_array($parser->parse(__DIR__.'/../../../Fixtures/csv/bgz_bnp_paribas.csv'));

    expect($rows)->toHaveCount(3);

    /** @var ParsedTransaction $netflix */
    $netflix = $rows[0];
    expect($netflix->postedAt->toDateString())->toBe('2026-04-15')
        ->and($netflix->amount)->toBe('-49.99')
        ->and($netflix->currency)->toBe('PLN')
        ->and($netflix->description)->toBe('NETFLIX SUBSCRIPTION')
        ->and($netflix->counterparty)->toBe('NETFLIX INTERNATIONAL B.V.')
        ->and($netflix->balance)->toBe('1234.56');

    /** @var ParsedTransaction $salary */
    $salary = $rows[2];
    expect($salary->amount)->toBe('5000')
        ->and($salary->description)->toBe('WYNAGRODZENIE');
});
