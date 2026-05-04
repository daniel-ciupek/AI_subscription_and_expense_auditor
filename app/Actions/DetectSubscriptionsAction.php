<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\AiSubscriptionDetectorInterface;
use App\Enums\SubscriptionDetectionSource;
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
 *   - the median gap between consecutive charges falls into one of the
 *     accepted cycle windows (weekly, biweekly, monthly, quarterly, yearly),
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

    /**
     * Accepted billing-cycle windows. Each window is an inclusive [min, max]
     * range in days. The label is informational only — the UI derives its
     * own label from `billing_cycle_days` for backward compatibility.
     *
     * @var list<array{label: string, min: int, max: int}>
     */
    public const CYCLE_WINDOWS = [
        ['label' => 'weekly', 'min' => 6, 'max' => 8],
        ['label' => 'biweekly', 'min' => 13, 'max' => 15],
        ['label' => 'monthly', 'min' => 25, 'max' => 35],
        ['label' => 'quarterly', 'min' => 85, 'max' => 95],
        ['label' => 'yearly', 'min' => 350, 'max' => 380],
    ];

    public const AMOUNT_TOLERANCE = 0.10; // ±10 % of the median amount

    /**
     * Long enough to fit at least two yearly charges (~365d cycle) in the
     * window, with headroom for the second charge to slip a few weeks late.
     */
    public const LOOKBACK_DAYS = 400;

    public const DUPLICATE_AMOUNT_TOLERANCE = 0.15;

    public const DUPLICATE_MIN_TOKEN_LENGTH = 4;

    /**
     * Minimum confidence the AI fallback must report to promote an
     * ambiguous group to a subscription. Anything lower is treated as
     * "not sure" and discarded.
     */
    public const AI_CONFIDENCE_THRESHOLD = 0.7;

    /**
     * Hard cap on how many ambiguous groups we forward to the model in a
     * single detection run. Cost control.
     */
    public const AI_MAX_GROUPS_PER_RUN = 10;

    public function __construct(
        private readonly ?AiSubscriptionDetectorInterface $aiDetector = null,
    ) {}

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
        /** @var list<Collection<int, Transaction>> $ambiguous */
        $ambiguous = [];

        foreach ($groups as $key => $group) {
            if ($key === '' || $group->count() < self::MIN_OCCURRENCES) {
                continue;
            }

            $analysis = $this->analyze($group);
            if ($analysis === null) {
                $ambiguous[] = $group;

                continue;
            }

            $persisted[] = $this->upsert($user, $analysis, SubscriptionDetectionSource::Rule);
        }

        if ($this->aiDetector !== null && $ambiguous !== []) {
            $persisted = array_merge(
                $persisted,
                $this->runAiFallback($user, $ambiguous),
            );
        }

        $this->markDuplicates($user);

        return $persisted;
    }

    /**
     * @param  list<Collection<int, Transaction>>  $ambiguousGroups
     * @return list<Subscription>
     */
    private function runAiFallback(User $user, array $ambiguousGroups): array
    {
        if ($this->aiDetector === null) {
            return [];
        }

        $persisted = [];
        $budget = self::AI_MAX_GROUPS_PER_RUN;

        foreach ($ambiguousGroups as $group) {
            if ($budget <= 0) {
                break;
            }
            $budget--;

            $payload = $this->groupToPayload($group);
            $result = $this->aiDetector->detect($payload);
            if ($result === null) {
                continue;
            }

            if ($result->confidence < self::AI_CONFIDENCE_THRESHOLD) {
                continue;
            }

            if (! self::isAcceptedCycle($result->billingCycleDays)) {
                continue;
            }

            $latest = $group->sortByDesc(fn (Transaction $tx) => $tx->posted_at->timestamp)->first();
            if ($latest === null) {
                continue;
            }

            $analysis = [
                'name' => $result->name !== '' ? $result->name : $latest->description,
                'amount' => number_format($result->amount, 2, '.', ''),
                'currency' => $result->currency !== '' ? $result->currency : 'PLN',
                'billing_cycle_days' => $result->billingCycleDays,
                'last_charge_at' => CarbonImmutable::instance($latest->posted_at),
                'category_id' => $this->modeCategoryId($group->all()),
            ];

            $persisted[] = $this->upsert($user, $analysis, SubscriptionDetectionSource::Ai);
        }

        return $persisted;
    }

    /**
     * @param  Collection<int, Transaction>  $group
     * @return list<array{posted_at: string, amount: float, description: string}>
     */
    private function groupToPayload(Collection $group): array
    {
        return array_values(
            $group->sortBy(fn (Transaction $tx) => $tx->posted_at->timestamp)
                ->values()
                ->map(static fn (Transaction $tx): array => [
                    'posted_at' => $tx->posted_at->toDateString(),
                    'amount' => round((float) $tx->amount, 2),
                    'description' => $tx->description,
                ])
                ->all(),
        );
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
        if (! self::isAcceptedCycle($cycle)) {
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
    private function upsert(User $user, array $data, SubscriptionDetectionSource $source): Subscription
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
                'detection_source' => $source,
            ],
        );
    }

    public static function isAcceptedCycle(int $cycleDays): bool
    {
        foreach (self::CYCLE_WINDOWS as $window) {
            if ($cycleDays >= $window['min'] && $cycleDays <= $window['max']) {
                return true;
            }
        }

        return false;
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
