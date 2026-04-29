<?php

declare(strict_types=1);

use App\DTOs\ParsedTransaction;
use App\Enums\Bank;
use App\Services\Parsers\BgzBnpParibasCsvParser;

it('claims BGŻ BNP Paribas headers', function () {
    $parser = new BgzBnpParibasCsvParser;

    expect($parser->bank())->toBe(Bank::BgzBnpParibas);
    expect($parser->matchesHeaders([
        'Data transakcji', 'Data zaksięgowania', 'Data odrzucenia', 'Kwota', 'Waluta',
        'Nadawca', 'Odbiorca', 'Opis', 'Produkt', 'Typ transakcji', 'Kwota zlecenia',
        'Waluta zlecenia', 'Status', 'Saldo po transakcji',
    ]))->toBeTrue();
});

it('rejects unrelated headers', function () {
    $parser = new BgzBnpParibasCsvParser;

    expect($parser->matchesHeaders(['Date', 'Description', 'Amount']))->toBeFalse();
});

it('parses settled BGŻ rows and skips pending or rejected ones', function () {
    $parser = new BgzBnpParibasCsvParser;
    $rows = iterator_to_array($parser->parse(__DIR__.'/../../../Fixtures/csv/bgz_bnp_paribas.csv'));

    // 5 rows in fixture, 2 are skipped (W trakcie realizacji + Odrzucona) = 3 settled.
    expect($rows)->toHaveCount(3);

    /** @var ParsedTransaction $card */
    $card = $rows[0];
    expect($card->postedAt->toDateString())->toBe('2026-04-29') // booking date used
        ->and($card->amount)->toBe('-98.94')
        ->and($card->currency)->toBe('PLN')
        ->and($card->balance)->toBe('17353.63'); // thin space stripped

    /** @var ParsedTransaction $netflix */
    $netflix = $rows[1];
    expect($netflix->description)->toBe('NETFLIX SUBSCRIPTION')
        ->and($netflix->counterparty)->toBe('NETFLIX INTERNATIONAL B.V.');

    /** @var ParsedTransaction $salary */
    $salary = $rows[2];
    expect($salary->amount)->toBe('5000')
        ->and($salary->counterparty)->toBe('ACME SP. Z O.O.')
        ->and($salary->description)->toContain('WYNAGRODZENIE');
});

it('strips minus-with-space and Polish thousand separators from amounts', function () {
    $parser = new class extends BgzBnpParibasCsvParser
    {
        public function publicParseAmount(string $value): string
        {
            return $this->parseAmount($value);
        }
    };

    expect($parser->publicParseAmount('- 50,17'))->toBe('-50.17');
    expect($parser->publicParseAmount("17\u{00A0}353,63"))->toBe('17353.63'); // NBSP
    expect($parser->publicParseAmount('1 234,56'))->toBe('1234.56');
});
