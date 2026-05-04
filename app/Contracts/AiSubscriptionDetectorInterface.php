<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\AiSubscriptionDetection;

/**
 * Strategy for the AI fallback path of subscription detection.
 *
 * The rule-based detector promotes any group that fits a known cycle
 * window with consistent amounts. Groups that pass MIN_OCCURRENCES but
 * fail those rules (e.g., median cycle 50d, or amount spread > 10 %)
 * are forwarded here. An implementation may decide:
 *   - return null → rule rejection stands (default Fake behavior),
 *   - return an AiSubscriptionDetection → action persists as a
 *     subscription with detection_source='ai'.
 *
 * Implementations must:
 *   - cap their own cost (timeout, retries, caching),
 *   - validate the model output before returning a non-null result,
 *   - never throw — log and return null on any failure.
 */
interface AiSubscriptionDetectorInterface
{
    /**
     * Identifier persisted alongside AI-detected subscriptions so we can
     * re-evaluate after a prompt change without losing audit trail.
     */
    public function version(): string;

    /**
     * @param  list<array{posted_at: string, amount: float, description: string}>  $transactions
     *                                                                                            transactions in chronological order, all sharing the same merchant
     *                                                                                            normalization key
     */
    public function detect(array $transactions): ?AiSubscriptionDetection;
}
