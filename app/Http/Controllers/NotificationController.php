<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $rows = $user->notifications()
            ->latest()
            ->limit(100)
            ->get();

        $notifications = $rows->map(static function (DatabaseNotification $row): array {
            /** @var array<string, mixed> $data */
            $data = $row->data;

            return [
                'id' => $row->id,
                'subscription_id' => $data['subscription_id'] ?? null,
                'subscription_name' => $data['subscription_name'] ?? '',
                'amount' => isset($data['amount']) ? (float) $data['amount'] : 0.0,
                'currency' => $data['currency'] ?? '',
                'expected_at' => $data['expected_at'] ?? null,
                'read_at' => $row->read_at?->toIso8601String(),
                'created_at' => $row->created_at?->toIso8601String(),
            ];
        })->all();

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $notification = $user->notifications()->whereKey($id)->first();
        if ($notification === null) {
            abort(404);
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        $subscriptionId = $notification->data['subscription_id'] ?? null;
        if (is_int($subscriptionId)) {
            return redirect()->route('subscriptions.show', $subscriptionId);
        }

        return redirect()->route('notifications.index');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $user->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
