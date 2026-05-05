<?php

declare(strict_types=1);

use App\Enums\Bank;
use App\Services\BankDetector;

it('auto-detects each supported bank from its CSV headers', function (string $fixture, Bank $expected) {
    $detector = new BankDetector;
    $parser = $detector->detect(__DIR__.'/../../Fixtures/csv/'.$fixture);

    expect($parser)->not->toBeNull();
    expect($parser->bank())->toBe($expected);
})->with([
    ['bgz_bnp_paribas.csv', Bank::BgzBnpParibas],
    ['mbank.csv', Bank::MBank],
    ['pko_bp.csv', Bank::PkoBp],
    ['ing.csv', Bank::Ing],
    ['santander.csv', Bank::Santander],
]);

it('returns null when no parser matches the headers', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($tmp, "foo,bar,baz\n1,2,3\n");

    $detector = new BankDetector;
    expect($detector->detect($tmp))->toBeNull();

    unlink($tmp);
});

it('looks up parser by bank enum', function () {
    $detector = new BankDetector;

    expect($detector->parserFor(Bank::MBank)->bank())->toBe(Bank::MBank);
    expect($detector->parserFor(Bank::BgzBnpParibas)->bank())->toBe(Bank::BgzBnpParibas);
});
