<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'categoryBreakdown' => $this->buildCategoryBreakdown($user->id),
        ]);
    }

    /**
     * @return array<int, array{slug: string, name: string, color: string, total: float}>
     */
    private function buildCategoryBreakdown(int $userId): array
    {
        $rows = DB::table('transactions')
            ->where('user_id', $userId)
            ->where('amount', '<', 0)
            ->groupBy('category_id')
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        /** @var array<int, Category> $categoriesById */
        $categoriesById = Category::query()
            ->get(['id', 'name', 'slug', 'color'])
            ->keyBy('id')
            ->all();

        return $rows
            ->map(function ($row) use ($categoriesById): array {
                $total = abs((float) $row->total);
                $category = $row->category_id !== null
                    ? ($categoriesById[$row->category_id] ?? null)
                    : null;

                if (! $category instanceof Category) {
                    return [
                        'slug' => 'uncategorized',
                        'name' => 'Uncategorized',
                        'color' => '#A1A1AA',
                        'total' => $total,
                    ];
                }

                return [
                    'slug' => $category->slug,
                    'name' => $category->name,
                    'color' => $category->color,
                    'total' => $total,
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();
    }
}
