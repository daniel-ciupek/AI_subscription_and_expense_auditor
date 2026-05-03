<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\UpcomingChargeNotification;
use Illuminate\Notifications\DatabaseNotification;

it('redirects guests away from the notifications index', function () {
    $this->get(route('notifications.index'))->assertRedirect(route('login'));
});

it('lists database notifications for the owner only', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $alice->notify(new UpcomingChargeNotification(
        subscriptionId: 1,
        subscriptionName: 'NETFLIX',
        amount: 49.99,
        currency: 'PLN',
        expectedAt: '2026-05-06',
    ));
    $bob->notify(new UpcomingChargeNotification(
        subscriptionId: 2,
        subscriptionName: 'BOB SECRET',
        amount: 99.0,
        currency: 'PLN',
        expectedAt: '2026-05-08',
    ));

    $this->actingAs($alice)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page->component('Notifications/Index')
                ->has('notifications', 1)
                ->where('notifications.0.subscription_name', 'NETFLIX'),
        );
});

it('marks a single notification as read and redirects to the subscription', function () {
    $user = User::factory()->create();
    $user->notify(new UpcomingChargeNotification(
        subscriptionId: 42,
        subscriptionName: 'NETFLIX',
        amount: 49.99,
        currency: 'PLN',
        expectedAt: '2026-05-06',
    ));

    /** @var DatabaseNotification $notification */
    $notification = $user->notifications()->firstOrFail();
    expect($notification->read_at)->toBeNull();

    $this->actingAs($user)
        ->post(route('notifications.read', $notification->id))
        ->assertRedirect(route('subscriptions.show', 42));

    $notification->refresh();
    expect($notification->read_at)->not->toBeNull();
});

it('exposes the unread count via shared inertia props', function () {
    $user = User::factory()->create();
    $user->notify(new UpcomingChargeNotification(
        subscriptionId: 1,
        subscriptionName: 'A',
        amount: 1.0,
        currency: 'PLN',
        expectedAt: '2026-05-06',
    ));
    $user->notify(new UpcomingChargeNotification(
        subscriptionId: 2,
        subscriptionName: 'B',
        amount: 2.0,
        currency: 'PLN',
        expectedAt: '2026-05-07',
    ));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(
            fn ($page) => $page->where('auth.unreadNotificationsCount', 2),
        );
});

it('marks all notifications as read', function () {
    $user = User::factory()->create();
    $user->notify(new UpcomingChargeNotification(
        subscriptionId: 1,
        subscriptionName: 'A',
        amount: 1.0,
        currency: 'PLN',
        expectedAt: '2026-05-06',
    ));
    $user->notify(new UpcomingChargeNotification(
        subscriptionId: 2,
        subscriptionName: 'B',
        amount: 2.0,
        currency: 'PLN',
        expectedAt: '2026-05-07',
    ));

    expect($user->unreadNotifications()->count())->toBe(2);

    $this->actingAs($user)
        ->from(route('notifications.index'))
        ->post(route('notifications.read-all'))
        ->assertRedirect(route('notifications.index'))
        ->assertSessionHas('success');

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('returns 404 when one user tries to mark another user\'s notification as read', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $bob->notify(new UpcomingChargeNotification(
        subscriptionId: 1,
        subscriptionName: 'BOB',
        amount: 1.0,
        currency: 'PLN',
        expectedAt: '2026-05-06',
    ));
    /** @var DatabaseNotification $bobNotif */
    $bobNotif = $bob->notifications()->firstOrFail();

    $this->actingAs($alice)
        ->post(route('notifications.read', $bobNotif->id))
        ->assertNotFound();

    expect($bobNotif->refresh()->read_at)->toBeNull();
});
