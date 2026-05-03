<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TransactionNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Rule-based subscription detector.
 *
 * Looks back across the user's expenses, groups them by normalized merchant
 * description, and promotes a group to a Subscription when it meets all of:
 *   - at least MIN_OCCURRENCES charges,
 *   - the median gap between consecutive charges falls in the monthly window
 *     [MIN_CYCLE_DAYS, MAX_CYCLE_DAYS],
 *   - the amount stays consistent (relative spread under AMOUNT_TOLERANCE).
 *
 * Each merchant produces at most one row in `subscriptions` — we upsert by
 * (user_id, name, billing_cycle_days) so re-running the action does not
 * duplicate. The "name" we persist is the most recent raw description so the
 * UI can show it as the user actually saw it on the statement.
 *
 * AI fallback for ambiguous groups is deliberately deferred — Etap 5.4 will
 * add it once we have signal on what "ambiguous" means in practice.
 */
class DetectSubscriptionsAction
{
    public const MIN_OCCURRENCES = 2;

    public const MIN_CYCLE_DAYS = 25;

    public const MAX_CYCLE_DAYS = 35;

    public const AMOUNT_TOLERANCE = 0.10; // ±10 % of the median amount

    public const LOOKBACK_DAYS = 180;

    public const DUPLICATE_AMOUNT_TOLERANCE = 0.15;

    public const DUPLICATE_MIN_TOKEN_LENGTH = 4;

    /**
     * @return array<int, Subscription>
     */
    public function handle(User $user): array
    {
        $cutoff = CarbonImmutable::now()->subDays(self::LOOKBACK_DAYS)->toDateString();

        $expenses = $user->transactions()
            ->where('amount', '<', 0)
            ->where('posted_at', '>=', $cutoff)
            ->orderBy('posted_at')
            ->orderBy('id')
            ->get(['id', 'posted_at', 'amount', 'description', 'category_id']);

        if ($expenses->isEmpty()) {
            return [];
        }

        /** @var Collection<string, Collection<int, Transaction>> $groups */
        $groups = $expenses->groupBy(
            fn (Transaction $tx): string => TransactionNormalizer::normalize($tx->description),
        );

        $persisted = [];
        foreach ($groups as $key => $group) {
            if ($key === '' || $group->count() < self::MIN_OCCURRENCES) {
                continue;
            }

            $analysis = $this->analyze($group);
            if ($analysis === null) {
                continue;
            }

            $persisted[] = $this->upsert($user, $analysis);
        }

        $this->markDuplicates($user);

        return $persisted;
    }

    /**
     * Pair up subscriptions that are likely the same recurring charge written
     * differently on the bank statement (e.g. "NETFLIX.COM" vs "NETFLIX EU"
     * billed at the same monthly cadence and similar amount). The newer row
     * gets `is_duplicate_of_id` pointing back to the older one so the UI can
     * highlight just one of them as the canonical entry.
     *
     * Subscriptions that the user has already resolved manually are skipped:
     * a `confirmed_duplicate` resolution keeps `is_duplicate_of_id` as-is, a
     * `kept_separate` resolution leaves the canonical state alone so the
     * detector can't re-flag it on the next run.
     */
    private function markDuplicates(User $user): void
    {
        $subscriptions = $user->subscriptions()->orderBy('id')->get();
        if ($subscriptions->count() < 2) {
            return;
        }

        $groups = $subscriptions->groupBy('billing_cycle_days');

        foreach ($groups as $cycleGroup) {
            if ($cycleGroup->count() < 2) {
                continue;
            }

            /** @var array<int, Subscription> $list */
            $list = $cycleGroup->values()->all();
            $count = count($list);

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($list[$j]->duplicate_resolution !== null) {
                        continue;
                    }

                    if ($this->areLikelyDuplicates($list[$i], $list[$j])) {
                        $list[$j]->update(['is_duplicate_of_id' => $list[$i]->id]);
                    }
                }
            }
        }
    }

    private function areLikelyDuplicates(Subscription $original, Subscription $candidate): bool
    {
        $tokensA = $this->meaningfulTokens($original->name);
        $tokensB = $this->meaningfulTokens($candidate->name);
        if (array_intersect($tokensA, $tokensB) === []) {
            return false;
        }

        $amountA = (float) $original->amount;
        $amountB = (float) $candidate->amount;
        if ($amountA <= 0.0 || $amountB <= 0.0) {
            return false;
        }

        $spread = abs($amountA - $amountB) / max($amountA, $amountB);

        return $spread <= self::DUPLICATE_AMOUNT_TOLERANCE;
    }

    /**
     * @return array<int, string>
     */
    private function meaningfulTokens(string $name): array
    {
        $normalized = TransactionNormalizer::normalize($name);
        if ($normalized === '') {
            return [];
        }

        return array_values(array_filter(
            explode(' ', $normalized),
            static fn (string $token): bool => mb_strlen($token) >= self::DUPLICATE_MIN_TOKEN_LENGTH,
        ));
    }

    /**
     * @param  Collection<int, Transaction>  $group
     * @return array{name: string, amount: string, currency: string, billing_cycle_days: int, last_charge_at: CarbonImmutable, category_id: int|null}|null
     */
    private function analyze(Collection $group): ?array
    {
        /** @var array<int, Transaction> $sorted */
        $sorted = $group->sortBy(fn (Transaction $tx) => $tx->posted_at->timestamp)->values()->all();

        $deltas = [];
        for ($i = 1, $n = count($sorted); $i < $n; $i++) {
            $prev = CarbonImmutable::instance($sorted[$i - 1]->posted_at);
            $curr = CarbonImmutable::instance($sorted[$i]->posted_at);
            $deltas[] = (int) $prev->diffInDays($curr, absolute: true);
        }

        if ($deltas === []) {
            return null;
        }

        $cycle = $this->median($deltas);
        if ($cycle < self::MIN_CYCLE_DAYS || $cycle > self::MAX_CYCLE_DAYS) {
            return null;
        }

        /** @var array<int, float> $absAmounts */
        $absAmounts = array_map(static fn (Transaction $tx): float => abs((float) $tx->amount), $sorted);
        $medianAmount = $this->medianFloat($absAmounts);
        if ($medianAmount <= 0.0) {
            return null;
        }

        foreach ($absAmounts as $value) {
            $relativeSpread = abs($value - $medianAmount) / $medianAmount;
            if ($relativeSpread > self::AMOUNT_TOLERANCE) {
                return null;
            }
        }

        $latest = $sorted[count($sorted) - 1];
        $categoryId = $this->modeCategoryId($sorted);

        return [
            'name' => $latest->description,
            'amount' => number_format($medianAmount, 2, '.', ''),
            'currency' => 'PLN',
            'billing_cycle_days' => $cycle,
            'last_charge_at' => CarbonImmutable::instance($latest->posted_at),
            'category_id' => $categoryId,
        ];
    }

    /**
     * @param  array{name: string, amount: string, currency: string, billing_cycle_days: int, last_charge_at: CarbonImmutable, category_id: int|null}  $data
     */
    private function upsert(User $user, array $data): Subscription
    {
        $next = $data['last_charge_at']->addDays($data['billing_cycle_days']);

        return Subscription::updateOrCreate(
            [
                'user_id' => $user->id,
                'name' => $data['name'],
                'billing_cycle_days' => $data['billing_cycle_days'],
            ],
            [
                'category_id' => $data['category_id'],
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'last_charge_at' => $data['last_charge_at']->toDateString(),
                'next_expected_charge_at' => $next->toDateString(),
            ],
        );
    }

    /**
     * @param  array<int, int>  $values
     */
    private function median(array $values): int
    {
        sort($values);
        $count = count($values);
        $middle = (int) floor(($count - 1) / 2);
        if ($count % 2 === 1) {
            return $values[$middle];
        }

        return (int) round(($values[$middle] + $values[$middle + 1]) / 2);
    }

    /**
     * @param  array<int, float>  $values
     */
    private function medianFloat(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = (int) floor(($count - 1) / 2);
        if ($count % 2 === 1) {
            return $values[$middle];
        }

        return ($values[$middle] + $values[$middle + 1]) / 2.0;
    }

    /**
     * @param  array<int, Transaction>  $transactions
     */
    private function modeCategoryId(array $transactions): ?int
    {
        $counts = [];
        foreach ($transactions as $tx) {
            if ($tx->category_id === null) {
                continue;
            }
            $counts[$tx->category_id] = ($counts[$tx->category_id] ?? 0) + 1;
        }

        if ($counts === []) {
            return null;
        }

        arsort($counts);

        return (int) array_key_first($counts);
    }
}
