<?php

declare(strict_types=1);

namespace App\Services\AiSubscriptionDetectors;

use App\Contracts\AiSubscriptionDetectorInterface;
use App\DTOs\AiSubscriptionDetection;

/**
 * Default no-op fallback. With AI_DRIVER=fake we never escalate
 * ambiguous groups to a model — the rule-based detector's verdict
 * stands. Tests that need a positive AI verdict swap this out via
 * the container.
 */
class FakeAiSubscriptionDetector implements AiSubscriptionDetectorInterface
{
    public function version(): string
    {
        return 'fake-v1';
    }

    public function detect(array $transactions): ?AiSubscriptionDetection
    {
        return null;
    }
}
