<?php

declare(strict_types=1);

use App\Actions\DetectSubscriptionsAction;
use App\Contracts\AiSubscriptionDetectorInterface;
use App\DTOs\AiSubscriptionDetection;
use App\Enums\DuplicateResolution;
use App\Enums\SubscriptionDetectionSource;
use App\Models\Category;
use App\Models\Import;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;
use Mockery\MockInterface;

beforeEach(function () {
    $this->seed(CategorySeeder::class);
    $this->user = User::factory()->create();
    $this->import = Import::factory()->for($this->user)->create();
});

function makeTx(int $userId, int $importId, string $description, string $amount, string $postedAt, ?int $categoryId = null): Transaction
{
    return Transaction::create([
        'user_id' => $userId,
        'import_id' => $importId,
        'category_id' => $categoryId,
        'posted_at' => $postedAt,
        'amount' => $amount,
        'currency' => 'PLN',
        'description' => $description,
        'counterparty' => null,
        'balance' => null,
        'hash' => hash('sha256', $description.'|'.$amount.'|'.$postedAt.'|'.uniqid('', true)),
    ]);
}

it('detects a monthly subscription from three consistent charges', function () {
    $today = CarbonImmutable::now();
    $subsCat = Category::query()->where('slug', 'subscriptions')->firstOrFail();

    foreach ([90, 60, 30] as $i => $daysAgo) {
        makeTx(
            $this->user->id,
            $this->import->id,
            'NETFLIX SUBSCRIPTION',
            '-49.99',
            $today->subDays($daysAgo)->toDateString(),
            $subsCat->id,
        );
    }

    $detected = (new DetectSubscriptionsAction)->handle($this->user);

    expect($detected)->toHaveCount(1);
    expect(Subscription::count())->toBe(1);

    $sub = Subscription::first();
    expect($sub->name)->toBe('NETFLIX SUBSCRIPTION');
    expect((float) $sub->amount)->toBe(49.99);
    expect($sub->billing_cycle_days)->toBe(30);
    expect($sub->category_id)->toBe($subsCat->id);
    expect($sub->last_charge_at->toDateString())->toBe($today->subDays(30)->toDateString());
    expect($sub->next_expected_charge_at->toDateString())
        ->toBe($today->subDays(30)->addDays(30)->toDateString());
});

it('groups transactions whose descriptions differ only by digit noise', function () {
    $today = CarbonImmutable::now();

    foreach ([90, 60, 30] as $i => $daysAgo) {
        makeTx(
            $this->user->id,
            $this->import->id,
            'BIEDRONKA '.($i + 1).str_pad((string) $i, 4, '0', STR_PAD_LEFT).' POZNAN',
            '-87.40',
            $today->subDays($daysAgo)->toDateString(),
        );
    }

    $detected = (new DetectSubscriptionsAction)->handle($this->user);

    expect($detected)->toHaveCount(1);
});

it('rejects groups with inconsistent amounts', function () {
    $today = CarbonImmutable::now();

    makeTx($this->user->id, $this->import->id, 'BIEDRONKA POZNAN', '-50.00', $today->subDays(90)->toDateString());
    makeTx($this->user->id, $this->import->id, 'BIEDRONKA POZNAN', '-150.00', $today->subDays(60)->toDateString());
    makeTx($this->user->id, $this->import->id, 'BIEDRONKA POZNAN', '-200.00', $today->subDays(30)->toDateString());

    expect((new DetectSubscriptionsAction)->handle($this->user))->toBe([]);
    expect(Subscription::count())->toBe(0);
});

it('rejects groups whose median cycle does not fall in any accepted window', function () {
    $today = CarbonImmutable::now();

    // ~50-day cycle — sits between monthly (25-35) and quarterly (85-95)
    // windows, so we treat it as noise rather than a subscription.
    foreach ([150, 100, 50] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'IRREGULAR', '-100.00', $today->subDays($daysAgo)->toDateString());
    }

    expect((new DetectSubscriptionsAction)->handle($this->user))->toBe([]);
});

it('skips one-off transactions', function () {
    $today = CarbonImmutable::now();
    makeTx($this->user->id, $this->import->id, 'IKEA POZNAN', '-1234.50', $today->subDays(30)->toDateString());

    expect((new DetectSubscriptionsAction)->handle($this->user))->toBe([]);
});

it('is idempotent — re-running upserts the same subscription', function () {
    $today = CarbonImmutable::now();

    foreach ([90, 60, 30] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'SPOTIFY PREMIUM', '-23.99', $today->subDays($daysAgo)->toDateString());
    }

    (new DetectSubscriptionsAction)->handle($this->user);
    (new DetectSubscriptionsAction)->handle($this->user);

    expect(Subscription::count())->toBe(1);
});

it('ignores income transactions', function () {
    $today = CarbonImmutable::now();
    foreach ([90, 60, 30] as $daysAgo) {
        // Positive amount → income, must not become a subscription even if recurring.
        makeTx($this->user->id, $this->import->id, 'WYNAGRODZENIE ACME', '5000.00', $today->subDays($daysAgo)->toDateString());
    }

    expect((new DetectSubscriptionsAction)->handle($this->user))->toBe([]);
});

it('ignores transactions older than the lookback window', function () {
    $today = CarbonImmutable::now();
    // Lookback is 400d so go further back than that; 30-day cadence would
    // otherwise happily promote these to a monthly subscription.
    foreach ([500, 470, 440] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'NETFLIX', '-49.99', $today->subDays($daysAgo)->toDateString());
    }

    expect((new DetectSubscriptionsAction)->handle($this->user))->toBe([]);
});

it('flags a near-identical subscription as a duplicate of the older one', function () {
    $today = CarbonImmutable::now();

    foreach ([100, 70, 40] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'NETFLIX.COM 49.99 PLN', '-49.99', $today->subDays($daysAgo)->toDateString());
    }
    foreach ([95, 65, 35] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'NETFLIX EU SUBSCRIPTION', '-49.99', $today->subDays($daysAgo)->toDateString());
    }

    (new DetectSubscriptionsAction)->handle($this->user);

    expect(Subscription::count())->toBe(2);
    $duplicates = Subscription::whereNotNull('is_duplicate_of_id')->get();
    expect($duplicates)->toHaveCount(1);
    expect($duplicates->first()->is_duplicate_of_id)
        ->toBe(Subscription::orderBy('id')->first()->id);
});

it('does not flag two subscriptions with no shared meaningful tokens', function () {
    $today = CarbonImmutable::now();

    foreach ([90, 60, 30] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'NETFLIX SUBSCRIPTION', '-49.99', $today->subDays($daysAgo)->toDateString());
    }
    foreach ([85, 55, 25] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'SPOTIFY PREMIUM', '-49.99', $today->subDays($daysAgo)->toDateString());
    }

    (new DetectSubscriptionsAction)->handle($this->user);

    expect(Subscription::count())->toBe(2);
    expect(Subscription::whereNotNull('is_duplicate_of_id')->count())->toBe(0);
});

it('does not flag duplicates when amounts diverge too much', function () {
    $today = CarbonImmutable::now();

    foreach ([90, 60, 30] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'NETFLIX STANDARD', '-39.99', $today->subDays($daysAgo)->toDateString());
    }
    foreach ([85, 55, 25] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'NETFLIX PREMIUM', '-67.00', $today->subDays($daysAgo)->toDateString());
    }

    (new DetectSubscriptionsAction)->handle($this->user);

    expect(Subscription::count())->toBe(2);
    expect(Subscription::whereNotNull('is_duplicate_of_id')->count())->toBe(0);
});

it('inherits the most common category from the underlying transactions', function () {
    $today = CarbonImmutable::now();
    $subs = Category::query()->where('slug', 'subscriptions')->firstOrFail();
    $other = Category::query()->where('slug', 'other')->firstOrFail();

    makeTx($this->user->id, $this->import->id, 'NETFLIX', '-49.99', $today->subDays(90)->toDateString(), $subs->id);
    makeTx($this->user->id, $this->import->id, 'NETFLIX', '-49.99', $today->subDays(60)->toDateString(), $subs->id);
    makeTx($this->user->id, $this->import->id, 'NETFLIX', '-49.99', $today->subDays(30)->toDateString(), $other->id);

    (new DetectSubscriptionsAction)->handle($this->user);

    expect(Subscription::first()->category_id)->toBe($subs->id);
});

it('respects a kept_separate resolution and does not re-flag the subscription', function () {
    $today = CarbonImmutable::now();

    foreach ([100, 70, 40] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'NETFLIX.COM 49.99 PLN', '-49.99', $today->subDays($daysAgo)->toDateString());
    }
    foreach ([95, 65, 35] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'NETFLIX EU SUBSCRIPTION', '-49.99', $today->subDays($daysAgo)->toDateString());
    }

    (new DetectSubscriptionsAction)->handle($this->user);

    // First detection flagged the second one as duplicate. User says: keep separate.
    $second = Subscription::orderBy('id')->skip(1)->first();
    $second->update([
        'is_duplicate_of_id' => null,
        'duplicate_resolution' => DuplicateResolution::KeptSeparate,
    ]);

    // Re-run detection. The kept_separate resolution must protect the canonical state.
    (new DetectSubscriptionsAction)->handle($this->user);

    $second->refresh();
    expect($second->is_duplicate_of_id)->toBeNull();
    expect($second->duplicate_resolution)->toBe(DuplicateResolution::KeptSeparate);
});

it('detects a weekly subscription (7-day cadence)', function () {
    $today = CarbonImmutable::now();

    foreach ([28, 21, 14, 7] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'WEEKLY MEAL KIT', '-49.00', $today->subDays($daysAgo)->toDateString());
    }

    $detected = (new DetectSubscriptionsAction)->handle($this->user);

    expect($detected)->toHaveCount(1);
    $sub = Subscription::first();
    expect($sub->billing_cycle_days)->toBe(7);
    expect($sub->name)->toBe('WEEKLY MEAL KIT');
});

it('detects a biweekly subscription (14-day cadence)', function () {
    $today = CarbonImmutable::now();

    foreach ([56, 42, 28, 14] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'PAYPAL EVERY OTHER WEEK', '-19.99', $today->subDays($daysAgo)->toDateString());
    }

    $detected = (new DetectSubscriptionsAction)->handle($this->user);

    expect($detected)->toHaveCount(1);
    $sub = Subscription::first();
    expect($sub->billing_cycle_days)->toBe(14);
});

it('detects a quarterly subscription (~90-day cadence)', function () {
    $today = CarbonImmutable::now();

    foreach ([270, 180, 90] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'OFFICE 365 QUARTERLY', '-119.99', $today->subDays($daysAgo)->toDateString());
    }

    $detected = (new DetectSubscriptionsAction)->handle($this->user);

    expect($detected)->toHaveCount(1);
    $sub = Subscription::first();
    expect($sub->billing_cycle_days)->toBe(90);
});

it('detects a yearly subscription (~365-day cadence)', function () {
    $today = CarbonImmutable::now();

    // Two yearly charges within the 400-day lookback window.
    foreach ([395, 30] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'DOMAIN RENEWAL', '-65.00', $today->subDays($daysAgo)->toDateString());
    }

    $detected = (new DetectSubscriptionsAction)->handle($this->user);

    expect($detected)->toHaveCount(1);
    $sub = Subscription::first();
    expect($sub->billing_cycle_days)->toBe(365);
});

it('rejects a 200-day cadence that sits between quarterly and yearly windows', function () {
    $today = CarbonImmutable::now();

    foreach ([400, 200] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'IRREGULAR PAY', '-100.00', $today->subDays($daysAgo)->toDateString());
    }

    expect((new DetectSubscriptionsAction)->handle($this->user))->toBe([]);
});

it('exposes accepted cycles via isAcceptedCycle for documentation', function () {
    expect(DetectSubscriptionsAction::isAcceptedCycle(7))->toBeTrue();
    expect(DetectSubscriptionsAction::isAcceptedCycle(14))->toBeTrue();
    expect(DetectSubscriptionsAction::isAcceptedCycle(30))->toBeTrue();
    expect(DetectSubscriptionsAction::isAcceptedCycle(90))->toBeTrue();
    expect(DetectSubscriptionsAction::isAcceptedCycle(365))->toBeTrue();
    expect(DetectSubscriptionsAction::isAcceptedCycle(50))->toBeFalse();
    expect(DetectSubscriptionsAction::isAcceptedCycle(200))->toBeFalse();
    expect(DetectSubscriptionsAction::isAcceptedCycle(400))->toBeFalse();
});

it('respects a confirmed_duplicate resolution and leaves the flag in place', function () {
    $today = CarbonImmutable::now();

    foreach ([100, 70, 40] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'NETFLIX.COM 49.99 PLN', '-49.99', $today->subDays($daysAgo)->toDateString());
    }
    foreach ([95, 65, 35] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'NETFLIX EU SUBSCRIPTION', '-49.99', $today->subDays($daysAgo)->toDateString());
    }

    (new DetectSubscriptionsAction)->handle($this->user);

    $first = Subscription::orderBy('id')->first();
    $second = Subscription::orderBy('id')->skip(1)->first();
    $second->update([
        'duplicate_resolution' => DuplicateResolution::ConfirmedDuplicate,
    ]);

    // Re-run. The flag should be untouched because user confirmed it.
    (new DetectSubscriptionsAction)->handle($this->user);

    $second->refresh();
    expect($second->is_duplicate_of_id)->toBe($first->id);
    expect($second->duplicate_resolution)
        ->toBe(DuplicateResolution::ConfirmedDuplicate);
});

it('marks rule-detected subscriptions with detection_source=rule', function () {
    $today = CarbonImmutable::now();
    foreach ([90, 60, 30] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'NETFLIX', '-49.99', $today->subDays($daysAgo)->toDateString());
    }

    (new DetectSubscriptionsAction)->handle($this->user);

    expect(Subscription::first()->detection_source)
        ->toBe(SubscriptionDetectionSource::Rule);
});

it('promotes an AI-confirmed ambiguous group to a subscription', function () {
    $today = CarbonImmutable::now();

    // Three charges with a ~50-day cadence — outside any rule window.
    foreach ([150, 100, 50] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'WEIRD GYM', '-79.99', $today->subDays($daysAgo)->toDateString());
    }

    /** @var MockInterface&AiSubscriptionDetectorInterface $ai */
    $ai = Mockery::mock(AiSubscriptionDetectorInterface::class);
    $ai->shouldReceive('version')->andReturn('test-v1');
    $ai->shouldReceive('detect')
        ->once()
        ->andReturn(new AiSubscriptionDetection(
            name: 'WEIRD GYM',
            billingCycleDays: 30,
            amount: 79.99,
            currency: 'PLN',
            confidence: 0.85,
            rawResponse: ['driver' => 'test'],
        ));

    (new DetectSubscriptionsAction($ai))->handle($this->user);

    expect(Subscription::count())->toBe(1);
    $sub = Subscription::first();
    expect($sub->name)->toBe('WEIRD GYM');
    expect($sub->billing_cycle_days)->toBe(30);
    expect((float) $sub->amount)->toBe(79.99);
    expect($sub->detection_source)->toBe(SubscriptionDetectionSource::Ai);
});

it('skips an AI suggestion below the confidence threshold', function () {
    $today = CarbonImmutable::now();
    foreach ([150, 100, 50] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'MAYBE NOISE', '-50.00', $today->subDays($daysAgo)->toDateString());
    }

    /** @var MockInterface&AiSubscriptionDetectorInterface $ai */
    $ai = Mockery::mock(AiSubscriptionDetectorInterface::class);
    $ai->shouldReceive('version')->andReturn('test-v1');
    $ai->shouldReceive('detect')
        ->once()
        ->andReturn(new AiSubscriptionDetection(
            name: 'MAYBE NOISE',
            billingCycleDays: 30,
            amount: 50.00,
            currency: 'PLN',
            confidence: 0.5,
            rawResponse: [],
        ));

    (new DetectSubscriptionsAction($ai))->handle($this->user);

    expect(Subscription::count())->toBe(0);
});

it('rejects AI suggestions whose cycle does not fit any accepted window', function () {
    $today = CarbonImmutable::now();
    foreach ([150, 100, 50] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'OFF SCHEDULE', '-100.00', $today->subDays($daysAgo)->toDateString());
    }

    /** @var MockInterface&AiSubscriptionDetectorInterface $ai */
    $ai = Mockery::mock(AiSubscriptionDetectorInterface::class);
    $ai->shouldReceive('version')->andReturn('test-v1');
    $ai->shouldReceive('detect')
        ->once()
        ->andReturn(new AiSubscriptionDetection(
            name: 'OFF SCHEDULE',
            billingCycleDays: 50, // not in CYCLE_WINDOWS
            amount: 100.00,
            currency: 'PLN',
            confidence: 0.95,
            rawResponse: [],
        ));

    (new DetectSubscriptionsAction($ai))->handle($this->user);

    expect(Subscription::count())->toBe(0);
});

it('does not consult AI when rule-based detection already matches', function () {
    $today = CarbonImmutable::now();
    foreach ([90, 60, 30] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'NETFLIX', '-49.99', $today->subDays($daysAgo)->toDateString());
    }

    /** @var MockInterface&AiSubscriptionDetectorInterface $ai */
    $ai = Mockery::mock(AiSubscriptionDetectorInterface::class);
    $ai->shouldNotReceive('detect');

    (new DetectSubscriptionsAction($ai))->handle($this->user);

    expect(Subscription::count())->toBe(1);
});

it('caps the number of AI calls per detection run', function () {
    $today = CarbonImmutable::now();

    // Build (AI_MAX_GROUPS_PER_RUN + 5) ambiguous groups, all 50d cadence.
    // Distinct merchant labels (no digits — the normalizer strips them
    // and would otherwise collapse everything into one group).
    $labels = [
        'ALPHA', 'BETA', 'GAMMA', 'DELTA', 'EPSILON',
        'ZETA', 'ETA', 'THETA', 'IOTA', 'KAPPA',
        'LAMBDA', 'OMEGA', 'NU', 'XI', 'PI',
    ];
    $groupCount = DetectSubscriptionsAction::AI_MAX_GROUPS_PER_RUN + 5;
    for ($g = 0; $g < $groupCount; $g++) {
        foreach ([150, 100, 50] as $daysAgo) {
            makeTx($this->user->id, $this->import->id, $labels[$g].' GYM', '-9.99', $today->subDays($daysAgo)->toDateString());
        }
    }

    /** @var MockInterface&AiSubscriptionDetectorInterface $ai */
    $ai = Mockery::mock(AiSubscriptionDetectorInterface::class);
    $ai->shouldReceive('version')->andReturn('test-v1');
    // Expect exactly AI_MAX_GROUPS_PER_RUN calls — extras must be skipped.
    $ai->shouldReceive('detect')
        ->times(DetectSubscriptionsAction::AI_MAX_GROUPS_PER_RUN)
        ->andReturn(null);

    (new DetectSubscriptionsAction($ai))->handle($this->user);
});

it('returns an empty list when AI detector is null and no rule match', function () {
    $today = CarbonImmutable::now();
    foreach ([150, 100, 50] as $daysAgo) {
        makeTx($this->user->id, $this->import->id, 'IRREGULAR', '-100.00', $today->subDays($daysAgo)->toDateString());
    }

    expect((new DetectSubscriptionsAction)->handle($this->user))->toBe([]);
});
