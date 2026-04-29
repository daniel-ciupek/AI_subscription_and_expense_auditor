<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $transactionsCount = $user->transactions()->count();
        $subscriptionsCount = $user->subscriptions()->count();
        $monthlySubscriptionsTotal = (float) $user->subscriptions()->sum('amount');

        $recentTransactions = $user->transactions()
            ->latest('posted_at')
            ->latest('id')
            ->limit(10)
            ->get(['id', 'posted_at', 'amount', 'currency', 'description', 'counterparty', 'category_id'])
            ->map(fn ($tx): array => [
                'id' => $tx->id,
                'posted_at' => $tx->posted_at->toDateString(),
                'amount' => $tx->amount,
                'currency' => $tx->currency,
                'description' => $tx->description,
                'counterparty' => $tx->counterparty,
            ])
            ->all();

        return Inertia::render('Dashboard', [
            'stats' => [
                'transactions' => $transactionsCount,
                'subscriptions' => $subscriptionsCount,
                'monthly_subscriptions_total' => $monthlySubscriptionsTotal,
            ],
            'recentTransactions' => $recentTransactions,
        ]);
    }
}
