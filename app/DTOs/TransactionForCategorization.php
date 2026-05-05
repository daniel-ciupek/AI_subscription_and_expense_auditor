<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class TransactionForCategorization
{
    public function __construct(
        public int $id,
        public string $description,
        public string $amount,
    ) {}

    public function isExpense(): bool
    {
        return (float) $this->amount < 0;
    }
}
