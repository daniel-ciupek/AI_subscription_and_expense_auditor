<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class AiCategorization
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public int $transactionId,
        public string $categorySlug,
        public float $confidence,
        public array $rawResponse,
    ) {}
}
