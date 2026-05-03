<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DuplicateResolution;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Jobs\DetectSubscriptionsJob;
use App\Models\Category;
use App\Models\Subscription;
use App\Support\SubscriptionMonthlyCost;
use App\Support\TransactionNormalizer;
use Carbon\CarbonImmutable;
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
                static fn (array $sub): float => SubscriptionMonthlyCost::forCycle(
                    $sub['amount'],
                    $sub['billing_cycle_days'],
                ),
                $canonical,
            )),
            'duplicateCount' => count($subscriptions) - count($canonical),
            'transactionsCount' => $user->transactions()->count(),
        ]);
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

    public function show(Request $request, Subscription $subscription): Response
    {
        $user = $request->user();
        if ($user === null || $subscription->user_id !== $user->id) {
            abort(403);
        }

        $subscription->load('category:id,name,slug,color,icon');

        $key = TransactionNormalizer::normalize($subscription->name);
        $cutoff = CarbonImmutable::now()->subDays(365)->toDateString();

        $charges = $user->transactions()
            ->where('amount', '<', 0)
            ->where('posted_at', '>=', $cutoff)
            ->orderByDesc('posted_at')
            ->get(['id', 'posted_at', 'amount', 'description', 'counterparty'])
            ->filter(fn ($tx) => TransactionNormalizer::normalize($tx->description) === $key)
            ->values();

        $chargeAmounts = $charges->map(static fn ($tx): float => abs((float) $tx->amount))->all();
        $totalSpent = array_sum($chargeAmounts);
        $avgPerCharge = $chargeAmounts === [] ? 0.0 : $totalSpent / count($chargeAmounts);

        $duplicateOf = null;
        if ($subscription->is_duplicate_of_id !== null) {
            $original = Subscription::query()
                ->where('id', $subscription->is_duplicate_of_id)
                ->where('user_id', $user->id)
                ->first(['id', 'name']);
            if ($original !== null) {
                $duplicateOf = ['id' => $original->id, 'name' => $original->name];
            }
        }

        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'color'])
            ->map(static fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'color' => $category->color,
            ])
            ->all();

        return Inertia::render('Subscriptions/Show', [
            'subscription' => [
                'id' => $subscription->id,
                'name' => $subscription->name,
                'amount' => (float) $subscription->amount,
                'currency' => $subscription->currency,
                'billing_cycle_days' => $subscription->billing_cycle_days,
                'last_charge_at' => $subscription->last_charge_at->toDateString(),
                'next_expected_charge_at' => $subscription->next_expected_charge_at?->toDateString(),
                'category_id' => $subscription->category_id,
                'category' => $subscription->category === null ? null : [
                    'name' => $subscription->category->name,
                    'slug' => $subscription->category->slug,
                    'color' => $subscription->category->color,
                ],
                'is_duplicate_of' => $duplicateOf,
                'duplicate_resolution' => $subscription->duplicate_resolution?->value,
            ],
            'categories' => $categories,
            'monthlyCost' => SubscriptionMonthlyCost::forCycle(
                (float) $subscription->amount,
                $subscription->billing_cycle_days,
            ),
            'stats' => [
                'charge_count' => $charges->count(),
                'total_spent' => round($totalSpent, 2),
                'avg_per_charge' => round($avgPerCharge, 2),
                'lookback_days' => 365,
            ],
            'charges' => $charges->map(static fn ($tx): array => [
                'id' => $tx->id,
                'posted_at' => $tx->posted_at->toDateString(),
                'amount' => abs((float) $tx->amount),
                'description' => $tx->description,
                'counterparty' => $tx->counterparty,
            ])->all(),
        ]);
    }

    public function confirmDuplicate(Request $request, Subscription $subscription): RedirectResponse
    {
        $user = $request->user();
        if ($user === null || $subscription->user_id !== $user->id) {
            abort(403);
        }

        if ($subscription->is_duplicate_of_id === null) {
            return redirect()
                ->route('subscriptions.show', $subscription)
                ->with('flash', [
                    'type' => 'error',
                    'message' => 'This subscription is not flagged as a duplicate.',
                ]);
        }

        $subscription->update([
            'duplicate_resolution' => DuplicateResolution::ConfirmedDuplicate,
        ]);

        return redirect()
            ->route('subscriptions.show', $subscription)
            ->with('flash', [
                'type' => 'success',
                'message' => 'Marked as the same merchant. Detection will keep this flag.',
            ]);
    }

    public function keepSeparate(Request $request, Subscription $subscription): RedirectResponse
    {
        $user = $request->user();
        if ($user === null || $subscription->user_id !== $user->id) {
            abort(403);
        }

        $subscription->update([
            'is_duplicate_of_id' => null,
            'duplicate_resolution' => DuplicateResolution::KeptSeparate,
        ]);

        return redirect()
            ->route('subscriptions.show', $subscription)
            ->with('flash', [
                'type' => 'success',
                'message' => 'Kept as a separate subscription. Detection will not re-flag it.',
            ]);
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): RedirectResponse
    {
        /** @var array{name: string, amount: numeric-string|float, currency: string, billing_cycle_days: int, last_charge_at: string, category_id: int|null} $data */
        $data = $request->validated();

        $lastCharge = CarbonImmutable::parse($data['last_charge_at'])->startOfDay();

        $subscription->update([
            'name' => $data['name'],
            'amount' => $data['amount'],
            'currency' => strtoupper($data['currency']),
            'billing_cycle_days' => $data['billing_cycle_days'],
            'last_charge_at' => $lastCharge,
            'next_expected_charge_at' => $lastCharge->addDays($data['billing_cycle_days']),
            'category_id' => $data['category_id'] ?? null,
        ]);

        return redirect()
            ->route('subscriptions.show', $subscription)
            ->with('flash', [
                'type' => 'success',
                'message' => 'Subscription updated.',
            ]);
    }

    public function destroy(Request $request, Subscription $subscription): RedirectResponse
    {
        $user = $request->user();
        if ($user === null || $subscription->user_id !== $user->id) {
            abort(403);
        }

        // If anyone pointed at this subscription as their canonical, clear that
        // pointer first so we don't leave dangling foreign keys.
        Subscription::query()
            ->where('user_id', $user->id)
            ->where('is_duplicate_of_id', $subscription->id)
            ->update([
                'is_duplicate_of_id' => null,
                'duplicate_resolution' => null,
            ]);

        $subscription->delete();

        return redirect()
            ->route('subscriptions.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Subscription removed.',
            ]);
    }
}
