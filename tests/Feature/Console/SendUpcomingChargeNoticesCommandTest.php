<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use App\Notifications\UpcomingChargeNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

it('sends a notification for subscriptions charging in 3 days', function () {
    Notification::fake();
    $user = User::factory()->create();

    Subscription::create([
        'user_id' => $user->id,
        'name' => 'NETFLIX',
        'amount' => '49.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => CarbonImmutable::today()->subDays(27)->toDateString(),
        'next_expected_charge_at' => CarbonImmutable::today()->addDays(3)->toDateString(),
    ]);

    $this->artisan('subscriptions:send-upcoming-charge-notices')
        ->assertSuccessful();

    Notification::assertSentTo(
        $user,
        UpcomingChargeNotification::class,
        function (UpcomingChargeNotification $notification): bool {
            return $notification->subscriptionName === 'NETFLIX'
                && $notification->amount === 49.99;
        },
    );
});

it('skips subscriptions outside the notification window', function () {
    Notification::fake();
    $user = User::factory()->create();

    // 5 days out — outside default window of 3
    Subscription::create([
        'user_id' => $user->id,
        'name' => 'TOO FAR',
        'amount' => '10.00',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => CarbonImmutable::today()->subDays(25)->toDateString(),
        'next_expected_charge_at' => CarbonImmutable::today()->addDays(5)->toDateString(),
    ]);

    // Today — already happened
    Subscription::create([
        'user_id' => $user->id,
        'name' => 'TOO CLOSE',
        'amount' => '10.00',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => CarbonImmutable::today()->subDays(30)->toDateString(),
        'next_expected_charge_at' => CarbonImmutable::today()->toDateString(),
    ]);

    $this->artisan('subscriptions:send-upcoming-charge-notices')
        ->assertSuccessful();

    Notification::assertNothingSentTo($user);
});

it('respects the --days option', function () {
    Notification::fake();
    $user = User::factory()->create();

    Subscription::create([
        'user_id' => $user->id,
        'name' => 'WEEKLY HEADS UP',
        'amount' => '10.00',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => CarbonImmutable::today()->subDays(23)->toDateString(),
        'next_expected_charge_at' => CarbonImmutable::today()->addDays(7)->toDateString(),
    ]);

    $this->artisan('subscriptions:send-upcoming-charge-notices', ['--days' => 7])
        ->assertSuccessful();

    Notification::assertSentTo($user, UpcomingChargeNotification::class);
});

it('skips subscriptions flagged as duplicates', function () {
    Notification::fake();
    $user = User::factory()->create();

    $canonical = Subscription::create([
        'user_id' => $user->id,
        'name' => 'NETFLIX',
        'amount' => '49.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => CarbonImmutable::today()->subDays(27)->toDateString(),
        'next_expected_charge_at' => CarbonImmutable::today()->addDays(3)->toDateString(),
    ]);
    Subscription::create([
        'user_id' => $user->id,
        'name' => 'NETFLIX EU',
        'amount' => '49.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => CarbonImmutable::today()->subDays(27)->toDateString(),
        'next_expected_charge_at' => CarbonImmutable::today()->addDays(3)->toDateString(),
        'is_duplicate_of_id' => $canonical->id,
    ]);

    $this->artisan('subscriptions:send-upcoming-charge-notices')
        ->assertSuccessful();

    Notification::assertSentToTimes($user, UpcomingChargeNotification::class, 1);
});

it('does not send a duplicate notification for the same subscription/expected_at', function () {
    $user = User::factory()->create();
    $subscription = Subscription::create([
        'user_id' => $user->id,
        'name' => 'SPOTIFY',
        'amount' => '21.99',
        'currency' => 'PLN',
        'billing_cycle_days' => 30,
        'last_charge_at' => CarbonImmutable::today()->subDays(27)->toDateString(),
        'next_expected_charge_at' => CarbonImmutable::today()->addDays(3)->toDateString(),
    ]);

    // First run — actual notify (not faked).
    $this->artisan('subscriptions:send-upcoming-charge-notices')
        ->assertSuccessful();

    expect($user->notifications()->count())->toBe(1);

    // Second run on the same day — should be a no-op.
    $this->artisan('subscriptions:send-upcoming-charge-notices')
        ->assertSuccessful();

    expect($user->notifications()->count())->toBe(1);
    unset($subscription);
});

it('rejects negative --days', function () {
    Notification::fake();

    $this->artisan('subscriptions:send-upcoming-charge-notices', ['--days' => -1])
        ->assertFailed();

    Notification::assertNothingSent();
});
