<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UpcomingChargeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly int $subscriptionId,
        public readonly string $subscriptionName,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $expectedAt,
    ) {}

    public static function fromSubscription(Subscription $subscription): self
    {
        if ($subscription->next_expected_charge_at === null) {
            throw new \InvalidArgumentException(
                'Subscription has no next_expected_charge_at — cannot notify.',
            );
        }

        return new self(
            subscriptionId: $subscription->id,
            subscriptionName: $subscription->name,
            amount: (float) $subscription->amount,
            currency: $subscription->currency,
            expectedAt: $subscription->next_expected_charge_at->toDateString(),
        );
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $formattedAmount = number_format($this->amount, 2, '.', ' ');

        return (new MailMessage)
            ->subject("Upcoming charge: {$this->subscriptionName}")
            ->greeting('Heads up')
            ->line(sprintf(
                '%s is scheduled to charge %s %s on %s.',
                $this->subscriptionName,
                $formattedAmount,
                $this->currency,
                $this->expectedAt,
            ))
            ->line('You can review or pause this subscription in the auditor.')
            ->action('Open subscription', url('/subscriptions/'.$this->subscriptionId))
            ->line('If this is an unexpected charge, you may want to contact your bank.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscriptionId,
            'subscription_name' => $this->subscriptionName,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'expected_at' => $this->expectedAt,
        ];
    }
}
