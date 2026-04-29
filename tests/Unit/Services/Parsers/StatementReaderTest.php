<?php

declare(strict_types=1);

use App\Services\Parsers\StatementReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('reads CSV rows', function () {
    $reader = new StatementReader;
    $rows = iterator_to_array($reader->rows(__DIR__.'/../../../Fixtures/csv/bgz_bnp_paribas.csv'));

    expect($rows[0][0])->toBe('Data transakcji');
    expect(count($rows))->toBeGreaterThan(1);
});

it('reads XLSX rows produced by PhpSpreadsheet', function () {
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([
        ['Data transakcji', 'Kwota', 'Status'],
        ['2026-04-28', '- 98,94', 'Zrealizowana'],
    ]);
    (new Xlsx($spreadsheet))->save($tmp);

    $reader = new StatementReader;
    $rows = iterator_to_array($reader->rows($tmp));

    expect($rows[0])->toBe(['Data transakcji', 'Kwota', 'Status']);
    expect($rows[1])->toBe(['2026-04-28', '- 98,94', 'Zrealizowana']);

    unlink($tmp);
});

it('returns first non-empty row regardless of source format', function () {
    $reader = new StatementReader;
    $headers = $reader->firstRow(__DIR__.'/../../../Fixtures/csv/bgz_bnp_paribas.csv');

    expect($headers)->not->toBeNull();
    expect($headers[0])->toBe('Data transakcji');
});
