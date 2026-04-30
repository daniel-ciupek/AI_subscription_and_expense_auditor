<?php

declare(strict_types=1);

use App\Actions\DetectSubscriptionsAction;
use App\Jobs\DetectSubscriptionsJob;
use App\Models\Import;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\CategorySeeder;

beforeEach(function () {
    $this->seed(CategorySeeder::class);
});

it('runs the detector for the given user', function () {
    $user = User::factory()->create();
    $import = Import::factory()->for($user)->create();

    $today = CarbonImmutable::now();
    foreach ([90, 60, 30] as $daysAgo) {
        Transaction::create([
            'user_id' => $user->id,
            'import_id' => $import->id,
            'posted_at' => $today->subDays($daysAgo)->toDateString(),
            'amount' => '-49.99',
            'currency' => 'PLN',
            'description' => 'NETFLIX SUBSCRIPTION',
            'counterparty' => null,
            'balance' => null,
            'hash' => hash('sha256', "tx-{$daysAgo}"),
        ]);
    }

    (new DetectSubscriptionsJob($user->id))->handle(app(DetectSubscriptionsAction::class));

    expect(Subscription::count())->toBe(1);
});

it('is a no-op when the user has been deleted', function () {
    (new DetectSubscriptionsJob(999_999))->handle(app(DetectSubscriptionsAction::class));

    expect(Subscription::count())->toBe(0);
});
