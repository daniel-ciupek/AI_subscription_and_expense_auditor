<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a subscription row was first promoted from raw transactions.
 *
 * - Rule: rule-based detector — group passed cycle window + amount
 *   tolerance. Fast, free, and the default path.
 * - Ai: an AI fallback evaluated an ambiguous group (rule-rejected) and
 *   returned a confident match. Slower, costs an API call, surfaced in
 *   the UI so the user knows it wasn't deterministic.
 */
enum SubscriptionDetectionSource: string
{
    case Rule = 'rule';
    case Ai = 'ai';
}
