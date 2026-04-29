<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\AiCategorization;
use App\DTOs\TransactionForCategorization;

interface AiCategorizerInterface
{
    /**
     * Identifier persisted on the ai_categorizations row alongside each
     * categorization. Bumping this lets us re-run categorization after a
     * prompt change without losing the audit trail of older runs.
     */
    public function version(): string;

    /**
     * @param  iterable<TransactionForCategorization>  $transactions
     * @return array<int, AiCategorization> keyed by transaction id
     */
    public function categorize(iterable $transactions): array;
}
