<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Headline "monthly cost" for a subscription.
 *
 * Cycles already in the monthly window (28–32d) keep the statement amount
 * as-is — naive 30/cycle normalization turned a 12.00 PLN charge billed
 * every 29 days into a misleading "12.41 PLN/month". Cycles outside that
 * window (weekly, quarterly, yearly) still get normalized so the headline
 * number stays comparable.
 */
final class SubscriptionMonthlyCost
{
    public static function forCycle(float $amount, int $cycleDays): float
    {
        if ($cycleDays >= 28 && $cycleDays <= 32) {
            return $amount;
        }

        return $amount * (30 / max($cycleDays, 1));
    }
}
