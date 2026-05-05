<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\DetectSubscriptionsAction;
use App\Contracts\AiCategorizerInterface;
use App\Enums\Bank;
use App\Enums\ImportStatus;
use App\Jobs\CategorizeTransactionsJob;
use App\Models\Import;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AiCategorizers\FakeAiCategorizer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Populates a complete demo account so a fresh clone shows real data on
 * every dashboard widget without needing a CSV upload. Idempotent — safe
 * to re-run, deletes the demo user's data first so the dataset stays
 * deterministic.
 *
 * Login: demo@example.com / demo1234
 */
class DemoSeeder extends Seeder
{
    private const DEMO_EMAIL = 'demo@example.com';

    private const DEMO_PASSWORD = 'demo1234';

    public function run(): void
    {
        $this->call(CategorySeeder::class);

        $user = User::firstOrCreate(
            ['email' => self::DEMO_EMAIL],
            [
                'name' => 'Demo User',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'email_verified_at' => now(),
            ],
        );

        // Wipe prior demo data so re-seeding stays deterministic.
        $user->transactions()->delete();
        $user->subscriptions()->delete();
        $user->imports()->forceDelete();

        $import = Import::create([
            'user_id' => $user->id,
            'bank' => Bank::BgzBnpParibas,
            'original_filename' => 'demo-bgz-statement.xlsx',
            'status' => ImportStatus::Done,
            'transactions_count' => 0,
        ]);

        $today = CarbonImmutable::now()->startOfDay();
        $transactionIds = [];

        // 1. Recurring monthly subscriptions — these will be picked up by
        //    DetectSubscriptionsAction on the rule-based path.
        $subscriptionPlans = [
            ['name' => 'NETFLIX SUBSCRIPTION 49.99 PLN', 'amount' => '-49.99'],
            ['name' => 'SPOTIFY PREMIUM PL', 'amount' => '-23.99'],
            ['name' => 'ALLEGRO LITE PL', 'amount' => '-10.99'],
            ['name' => 'OPENAI CHATGPT PLUS', 'amount' => '-80.00'],
            ['name' => 'ICLOUD STORAGE 200GB', 'amount' => '-4.99'],
            // Intentional duplicate of NETFLIX SUBSCRIPTION — lets the
            // duplicate-detector + dashboard insights widget light up.
            ['name' => 'NETFLIX EU PREMIUM', 'amount' => '-49.99'],
        ];

        foreach ($subscriptionPlans as $plan) {
            foreach ([120, 90, 60, 30, 1] as $daysAgo) {
                $transactionIds[] = $this->createTransaction(
                    $user->id,
                    $import->id,
                    $today->subDays($daysAgo)->toDateString(),
                    $plan['amount'],
                    // Description must be identical across recurrences so the
                    // detector groups them; hash uniqueness comes from uniqid()
                    // baked into createTransaction().
                    $plan['name'],
                    $plan['name'],
                );
            }
        }

        // 2. Recurring income — every month-ish.
        foreach ([120, 90, 60, 30] as $daysAgo) {
            $transactionIds[] = $this->createTransaction(
                $user->id,
                $import->id,
                $today->subDays($daysAgo)->toDateString(),
                '8500.00',
                'WYNAGRODZENIE ACME SP Z O O',
                'WYNAGRODZENIE ACME SP Z O O',
            );
        }

        // 3. High-frequency groceries — varied amounts so they don't get
        //    flagged as subscriptions but feed the spending chart.
        $groceries = ['BIEDRONKA', 'LIDL', 'ŻABKA', 'CARREFOUR', 'AUCHAN'];
        for ($i = 0; $i < 60; $i++) {
            $store = $groceries[array_rand($groceries)];
            $daysAgo = random_int(1, 110);
            $amount = '-'.number_format(random_int(1500, 18000) / 100, 2, '.', '');
            $transactionIds[] = $this->createTransaction(
                $user->id,
                $import->id,
                $today->subDays($daysAgo)->toDateString(),
                $amount,
                $store.' '.random_int(1000, 9999).' POZNAN',
                $store,
            );
        }

        // 4. Transport — also irregular.
        $transport = ['UBER BV', 'BOLT EU', 'ORLEN STACJA', 'MPK POZNAN', 'PKP INTERCITY'];
        for ($i = 0; $i < 25; $i++) {
            $merchant = $transport[array_rand($transport)];
            $daysAgo = random_int(1, 110);
            $amount = '-'.number_format(random_int(800, 12000) / 100, 2, '.', '');
            $transactionIds[] = $this->createTransaction(
                $user->id,
                $import->id,
                $today->subDays($daysAgo)->toDateString(),
                $amount,
                $merchant.' '.random_int(100, 999),
                $merchant,
            );
        }

        // 5. Entertainment + bills, sparser.
        $other = [
            ['merchant' => 'STEAM PURCHASE', 'min' => 2000, 'max' => 18000],
            ['merchant' => 'KINO HELIOS', 'min' => 2500, 'max' => 5500],
            ['merchant' => 'ORANGE PL FAKTURA', 'min' => 6000, 'max' => 8000],
            ['merchant' => 'TAURON ENERGIA', 'min' => 12000, 'max' => 22000],
            ['merchant' => 'IKEA POZNAN', 'min' => 5000, 'max' => 80000],
            ['merchant' => 'RESTAURACJA POZNAN', 'min' => 3000, 'max' => 16000],
        ];
        for ($i = 0; $i < 25; $i++) {
            $row = $other[array_rand($other)];
            $daysAgo = random_int(1, 110);
            $amount = '-'.number_format(random_int($row['min'], $row['max']) / 100, 2, '.', '');
            $transactionIds[] = $this->createTransaction(
                $user->id,
                $import->id,
                $today->subDays($daysAgo)->toDateString(),
                $amount,
                $row['merchant'].' '.random_int(1, 999),
                $row['merchant'],
            );
        }

        $import->update(['transactions_count' => count($transactionIds)]);

        $this->categorizeAll($transactionIds);
        $reloaded = $user->fresh();
        if ($reloaded instanceof User) {
            (new DetectSubscriptionsAction)->handle($reloaded);
        }

        Log::info('Demo seeded', [
            'email' => self::DEMO_EMAIL,
            'password' => self::DEMO_PASSWORD,
            'transactions' => $user->transactions()->count(),
            'subscriptions' => $user->subscriptions()->count(),
        ]);
    }

    private function createTransaction(
        int $userId,
        int $importId,
        string $postedAt,
        string $amount,
        string $description,
        string $counterparty,
    ): int {
        $tx = Transaction::create([
            'user_id' => $userId,
            'import_id' => $importId,
            'posted_at' => $postedAt,
            'amount' => $amount,
            'currency' => 'PLN',
            'description' => $description,
            'counterparty' => $counterparty,
            'balance' => null,
            'hash' => hash('sha256', "demo|{$userId}|{$postedAt}|{$amount}|{$description}|".uniqid('', true)),
        ]);

        return $tx->id;
    }

    /**
     * @param  array<int, int>  $transactionIds
     */
    private function categorizeAll(array $transactionIds): void
    {
        // Run the same job the queue would, in chunks of 20, but synchronously
        // so the seeded dataset is fully ready when this method returns.
        $categorizer = new FakeAiCategorizer;
        app()->instance(AiCategorizerInterface::class, $categorizer);

        foreach (array_chunk($transactionIds, CategorizeTransactionsJob::MAX_BATCH) as $chunk) {
            (new CategorizeTransactionsJob($chunk))->handle($categorizer);
        }
    }
}
