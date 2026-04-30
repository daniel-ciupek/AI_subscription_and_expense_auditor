<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\DetectSubscriptionsJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $rows = $user->subscriptions()
            ->with('category:id,name,slug,color,icon')
            ->orderByDesc('amount')
            ->get([
                'id', 'category_id', 'name', 'amount', 'currency',
                'billing_cycle_days', 'last_charge_at', 'next_expected_charge_at',
                'is_duplicate_of_id',
            ]);

        $namesById = $rows->pluck('name', 'id');

        $subscriptions = $rows
            ->map(fn ($sub) => [
                'id' => $sub->id,
                'name' => $sub->name,
                'amount' => (float) $sub->amount,
                'currency' => $sub->currency,
                'billing_cycle_days' => $sub->billing_cycle_days,
                'last_charge_at' => $sub->last_charge_at->toDateString(),
                'next_expected_charge_at' => $sub->next_expected_charge_at?->toDateString(),
                'category' => $sub->category === null ? null : [
                    'name' => $sub->category->name,
                    'slug' => $sub->category->slug,
                    'color' => $sub->category->color,
                ],
                'is_duplicate_of_id' => $sub->is_duplicate_of_id,
                'duplicate_of_name' => $sub->is_duplicate_of_id !== null
                    ? ($namesById[$sub->is_duplicate_of_id] ?? null)
                    : null,
            ])
            ->all();

        // Monthly cost only counts canonical rows so duplicates aren't
        // double-billed in the headline number.
        $canonical = array_filter(
            $subscriptions,
            static fn (array $sub): bool => $sub['is_duplicate_of_id'] === null,
        );

        return Inertia::render('Subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'monthlyTotal' => array_sum(array_map(
                static fn (array $sub): float => $sub['amount'] * self::monthlyMultiplier($sub['billing_cycle_days']),
                $canonical,
            )),
            'duplicateCount' => count($subscriptions) - count($canonical),
            'transactionsCount' => $user->transactions()->count(),
        ]);
    }

    /**
     * Cycles already in the monthly window (28–32d) keep the statement amount
     * as-is — a 29-day cycle on a 12.00 PLN charge looks like "12.41 PLN/month"
     * with naive 30/cycle normalization, which doesn't match what the user sees
     * on the invoice. Weekly/quarterly/yearly cycles still get normalized so
     * the headline number stays comparable.
     */
    private static function monthlyMultiplier(int $cycleDays): float
    {
        if ($cycleDays >= 28 && $cycleDays <= 32) {
            return 1.0;
        }

        return 30 / max($cycleDays, 1);
    }

    public function detect(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        DetectSubscriptionsJob::dispatch($user->id);

        return redirect()
            ->route('subscriptions.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Subscription detection started. Refresh in a moment to see results.',
            ]);
    }
}
