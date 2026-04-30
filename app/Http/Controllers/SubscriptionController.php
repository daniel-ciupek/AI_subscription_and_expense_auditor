<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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

        $subscriptions = $user->subscriptions()
            ->with('category:id,name,slug,color,icon')
            ->orderByDesc('amount')
            ->get([
                'id', 'category_id', 'name', 'amount', 'currency',
                'billing_cycle_days', 'last_charge_at', 'next_expected_charge_at',
            ])
            ->map(fn ($sub): array => [
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
            ])
            ->all();

        return Inertia::render('Subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'monthlyTotal' => array_sum(array_map(
                static fn (array $sub): float => $sub['amount'] * (30 / max($sub['billing_cycle_days'], 1)),
                $subscriptions,
            )),
        ]);
    }
}
