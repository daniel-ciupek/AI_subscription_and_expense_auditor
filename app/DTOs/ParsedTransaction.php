<?php

declare(strict_types=1);

namespace App\DTOs;

use Carbon\CarbonImmutable;

final readonly class ParsedTransaction
{
    public function __construct(
        public CarbonImmutable $postedAt,
        public string $amount,
        public string $currency,
        public string $description,
        public ?string $counterparty,
        public ?string $balance,
    ) {}
}
