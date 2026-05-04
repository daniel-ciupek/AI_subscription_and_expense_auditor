<?php

declare(strict_types=1);

use App\Services\AiSubscriptionDetectors\DeepseekAiSubscriptionDetector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

function makeDetector(): DeepseekAiSubscriptionDetector
{
    return new DeepseekAiSubscriptionDetector(
        apiKey: 'test-key',
        model: 'deepseek-chat',
        baseUrl: 'https://api.deepseek.com/v1',
    );
}

function transactionsFixture(): array
{
    return [
        ['posted_at' => '2026-02-01', 'amount' => -49.99, 'description' => 'WEIRD GYM'],
        ['posted_at' => '2026-03-23', 'amount' => -49.99, 'description' => 'WEIRD GYM'],
        ['posted_at' => '2026-05-12', 'amount' => -49.99, 'description' => 'WEIRD GYM'],
    ];
}

it('returns a detection when the model confirms the subscription', function () {
    Http::fake([
        'api.deepseek.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'is_subscription' => true,
                        'name' => 'WEIRD GYM',
                        'billing_cycle_days' => 30,
                        'expected_amount' => 49.99,
                        'currency' => 'PLN',
                        'confidence' => 0.92,
                    ]),
                ],
            ]],
        ]),
    ]);

    $result = makeDetector()->detect(transactionsFixture());

    expect($result)->not->toBeNull();
    expect($result->name)->toBe('WEIRD GYM');
    expect($result->billingCycleDays)->toBe(30);
    expect($result->amount)->toBe(49.99);
    expect($result->confidence)->toBe(0.92);
});

it('returns null when the model says it is not a subscription', function () {
    Http::fake([
        'api.deepseek.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'is_subscription' => false,
                        'confidence' => 0.3,
                    ]),
                ],
            ]],
        ]),
    ]);

    expect(makeDetector()->detect(transactionsFixture()))->toBeNull();
});

it('returns null on schema violations (e.g., out-of-range cycle)', function () {
    Http::fake([
        'api.deepseek.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'is_subscription' => true,
                        'name' => 'WEIRD GYM',
                        'billing_cycle_days' => 47,
                        'expected_amount' => 49.99,
                        'confidence' => 0.95,
                    ]),
                ],
            ]],
        ]),
    ]);

    expect(makeDetector()->detect(transactionsFixture()))->toBeNull();
});

it('returns null on invalid JSON content', function () {
    Http::fake([
        'api.deepseek.com/*' => Http::response([
            'choices' => [[
                'message' => ['content' => 'not json at all { broken'],
            ]],
        ]),
    ]);

    expect(makeDetector()->detect(transactionsFixture()))->toBeNull();
});

it('returns null on non-2xx HTTP responses', function () {
    Http::fake([
        'api.deepseek.com/*' => Http::response(['error' => 'rate-limited'], 429),
    ]);

    expect(makeDetector()->detect(transactionsFixture()))->toBeNull();
});

it('returns null for empty input without calling the API', function () {
    Http::fake();

    expect(makeDetector()->detect([]))->toBeNull();

    Http::assertNothingSent();
});

it('caches results so repeated calls with the same group skip the API', function () {
    Http::fake([
        'api.deepseek.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'is_subscription' => true,
                        'name' => 'CACHED',
                        'billing_cycle_days' => 30,
                        'expected_amount' => 12.0,
                        'currency' => 'PLN',
                        'confidence' => 0.8,
                    ]),
                ],
            ]],
        ]),
    ]);

    $detector = makeDetector();
    $first = $detector->detect(transactionsFixture());
    $second = $detector->detect(transactionsFixture());

    expect($first)->not->toBeNull();
    expect($second)->not->toBeNull();
    expect($second->name)->toBe('CACHED');

    Http::assertSentCount(1);
});

it('caches negative results so repeat questions about the same noise stay free', function () {
    Http::fake([
        'api.deepseek.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'is_subscription' => false,
                        'confidence' => 0.2,
                    ]),
                ],
            ]],
        ]),
    ]);

    $detector = makeDetector();
    expect($detector->detect(transactionsFixture()))->toBeNull();
    expect($detector->detect(transactionsFixture()))->toBeNull();

    Http::assertSentCount(1);
});
