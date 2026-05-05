<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Result of asking the AI whether a group of transactions is a subscription.
 *
 * `null` is the "not a subscription" / "low confidence" / "API failed"
 * answer — the detector returns null and the action ignores the group.
 *
 * `confidence` is 0..1 from the model. The action enforces a threshold so
 * weak signals don't pollute the subscription list.
 */
final class AiSubscriptionDetection
{
    public function __construct(
        public readonly string $name,
        public readonly int $billingCycleDays,
        public readonly float $amount,
        public readonly string $currency,
        public readonly float $confidence,
        /** @var array<string, mixed> */
        public readonly array $rawResponse,
    ) {}
}
