<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\DetectSubscriptionsAction;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Re-runs subscription detection for a user. Triggered after the categorize
 * batch finishes so that DetectSubscriptionsAction sees fully-categorized
 * transactions when picking the dominant category for each subscription.
 */
class DetectSubscriptionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $userId) {}

    public function handle(DetectSubscriptionsAction $action): void
    {
        $user = User::find($this->userId);
        if ($user === null) {
            return;
        }

        $action->handle($user);
    }
}
