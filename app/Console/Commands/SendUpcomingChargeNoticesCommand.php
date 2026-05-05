<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\UpcomingChargeNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class SendUpcomingChargeNoticesCommand extends Command
{
    protected $signature = 'subscriptions:send-upcoming-charge-notices
        {--days=3 : How many days ahead of the next expected charge to notify}';

    protected $description = 'Notify users about subscription charges expected in N days';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days < 0) {
            $this->error('--days must be zero or positive.');

            return self::INVALID;
        }

        $target = CarbonImmutable::today()->addDays($days);

        $subscriptions = Subscription::query()
            ->whereDate('next_expected_charge_at', $target->toDateString())
            ->whereNull('is_duplicate_of_id')
            ->with('user:id,name,email')
            ->get();

        $sent = 0;
        $skipped = 0;
        foreach ($subscriptions as $subscription) {
            if ($subscription->user === null) {
                continue;
            }

            if ($this->alreadyNotified($subscription, $target->toDateString())) {
                $skipped++;

                continue;
            }

            $subscription->user->notify(
                UpcomingChargeNotification::fromSubscription($subscription),
            );
            $sent++;
        }

        $this->info("Upcoming charge notices: {$sent} sent, {$skipped} skipped (already notified).");

        return self::SUCCESS;
    }

    private function alreadyNotified(Subscription $subscription, string $expectedAt): bool
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $subscription->user_id)
            ->where('type', UpcomingChargeNotification::class)
            ->where('data->subscription_id', $subscription->id)
            ->where('data->expected_at', $expectedAt)
            ->exists();
    }
}
