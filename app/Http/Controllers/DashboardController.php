<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\User;
use App\Support\SubscriptionMonthlyCost;
use Carbon\CarbonImmutable;
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
            'spendingOverTime' => $this->buildSpendingOverTime($user->id),
            'topSubscriptions' => $this->buildTopSubscriptions($user),
        ]);
    }

    /**
     * Top 5 canonical subscriptions sorted by monthly cost (computed via
     * SubscriptionMonthlyCost so a weekly subscription outranks a small
     * monthly one when comparable).
     *
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     monthly_cost: float,
     *     currency: string,
     *     billing_cycle_days: int,
     *     category: array{name: string, slug: string, color: string}|null,
     * }>
     */
    private function buildTopSubscriptions(User $user): array
    {
        return $user->subscriptions()
            ->whereNull('is_duplicate_of_id')
            ->with('category:id,name,slug,color')
            ->get(['id', 'category_id', 'name', 'amount', 'currency', 'billing_cycle_days', 'is_duplicate_of_id'])
            ->map(fn ($sub): array => [
                'id' => $sub->id,
                'name' => $sub->name,
                'monthly_cost' => SubscriptionMonthlyCost::forCycle(
                    (float) $sub->amount,
                    $sub->billing_cycle_days,
                ),
                'currency' => $sub->currency,
                'billing_cycle_days' => $sub->billing_cycle_days,
                'category' => $sub->category === null ? null : [
                    'name' => $sub->category->name,
                    'slug' => $sub->category->slug,
                    'color' => $sub->category->color,
                ],
            ])
            ->sortByDesc('monthly_cost')
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * Daily expense totals for the last 90 days. Days with no expense are
     * filled in with 0 so the area chart renders a continuous baseline
     * instead of jumping between sparse points.
     *
     * @return array<int, array{date: string, total: float}>
     */
    private function buildSpendingOverTime(int $userId): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $cutoff = $today->subDays(89); // inclusive 90-day window

        $rows = DB::table('transactions')
            ->where('user_id', $userId)
            ->where('amount', '<', 0)
            ->where('posted_at', '>=', $cutoff->toDateString())
            ->groupBy('posted_at')
            ->select('posted_at', DB::raw('SUM(amount) as total'))
            ->get();

        /** @var array<string, float> $totalsByDate */
        $totalsByDate = [];
        foreach ($rows as $row) {
            $date = CarbonImmutable::parse((string) $row->posted_at)->toDateString();
            $totalsByDate[$date] = abs((float) $row->total);
        }

        $series = [];
        for ($cursor = $cutoff; $cursor->lessThanOrEqualTo($today); $cursor = $cursor->addDay()) {
            $key = $cursor->toDateString();
            $series[] = [
                'date' => $key,
                'total' => $totalsByDate[$key] ?? 0.0,
            ];
        }

        return $series;
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
