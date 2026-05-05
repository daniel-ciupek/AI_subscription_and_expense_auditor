<?php

declare(strict_types=1);

use App\DTOs\TransactionForCategorization;
use App\Services\AiCategorizers\DeepseekAiCategorizer;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function makeDeepseekCategorizer(): DeepseekAiCategorizer
{
    return new DeepseekAiCategorizer(
        apiKey: 'sk-test-123',
        model: 'deepseek-chat',
        baseUrl: 'https://api.deepseek.com/v1',
        timeoutSeconds: 5,
    );
}

function fakeDeepseekResponse(array $categorizations, int $status = 200): array
{
    return [
        'api.deepseek.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode(['categorizations' => $categorizations]),
                ],
            ]],
        ], $status),
    ];
}

it('returns an empty array when given no transactions (deepseek)', function () {
    Http::fake();

    expect(makeDeepseekCategorizer()->categorize([]))->toBe([]);

    Http::assertNothingSent();
});

it('parses a successful Deepseek response into AiCategorization DTOs', function () {
    Http::fake(fakeDeepseekResponse([
        ['id' => 1, 'category_slug' => 'subscriptions', 'confidence' => 0.94],
        ['id' => 2, 'category_slug' => 'food', 'confidence' => 0.81],
    ]));

    $results = makeDeepseekCategorizer()->categorize([
        new TransactionForCategorization(1, 'NETFLIX SUBSCRIPTION', '-49.99'),
        new TransactionForCategorization(2, 'BIEDRONKA', '-87.40'),
    ]);

    expect($results)->toHaveCount(2);
    expect($results[1]->categorySlug)->toBe('subscriptions');
    expect($results[1]->confidence)->toBe(0.94);
    expect($results[1]->rawResponse)->toHaveKey('driver', 'deepseek');
    expect($results[2]->categorySlug)->toBe('food');
});

it('sends Bearer auth and json_object response_format to deepseek', function () {
    Http::fake(fakeDeepseekResponse([
        ['id' => 1, 'category_slug' => 'food', 'confidence' => 0.9],
    ]));

    makeDeepseekCategorizer()->categorize([
        new TransactionForCategorization(1, 'BIEDRONKA', '-50.00'),
    ]);

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer sk-test-123')
            && data_get($request->data(), 'response_format.type') === 'json_object'
            && data_get($request->data(), 'model') === 'deepseek-chat';
    });
});

it('falls back to "other" for transactions missing in the deepseek response', function () {
    Http::fake(fakeDeepseekResponse([
        ['id' => 1, 'category_slug' => 'subscriptions', 'confidence' => 0.99],
    ]));

    $results = makeDeepseekCategorizer()->categorize([
        new TransactionForCategorization(1, 'NETFLIX', '-49.99'),
        new TransactionForCategorization(2, 'WHATEVER', '-10.00'),
    ]);

    expect($results[2]->categorySlug)->toBe('other');
    expect($results[2]->confidence)->toBe(0.0);
    expect($results[2]->rawResponse)
        ->toHaveKey('fallback', true)
        ->toHaveKey('reason', 'missing_in_response');
});

it('rejects unknown category slugs from deepseek and falls back for the whole batch', function () {
    Http::fake(fakeDeepseekResponse([
        ['id' => 1, 'category_slug' => 'cryptocurrencies', 'confidence' => 0.99],
    ]));

    $results = makeDeepseekCategorizer()->categorize([
        new TransactionForCategorization(1, 'BTC', '-100.00'),
    ]);

    expect($results[1]->categorySlug)->toBe('other');
    expect($results[1]->rawResponse)->toHaveKey('error', 'schema_violation');
    expect($results[1]->rawResponse)->toHaveKey('driver', 'deepseek');
});

it('falls back when deepseek returns invalid JSON', function () {
    Http::fake([
        'api.deepseek.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'not json at all']]],
        ], 200),
    ]);

    $results = makeDeepseekCategorizer()->categorize([
        new TransactionForCategorization(1, 'X', '-1.00'),
    ]);

    expect($results[1]->categorySlug)->toBe('other');
    expect($results[1]->rawResponse)->toHaveKey('error', 'invalid_json');
});

it('retries 5xx deepseek responses and falls back when retries are exhausted', function () {
    Http::fake([
        'api.deepseek.com/*' => Http::sequence()
            ->push('upstream timeout', 503)
            ->push('upstream timeout', 503)
            ->push('upstream timeout', 503)
            ->push('upstream timeout', 503),
    ]);

    $results = makeDeepseekCategorizer()->categorize([
        new TransactionForCategorization(1, 'NETFLIX', '-49.99'),
    ]);

    expect($results[1]->categorySlug)->toBe('other');
    expect($results[1]->rawResponse)->toHaveKey('error', 'http_503');
});

it('reports the configured deepseek prompt version', function () {
    expect(makeDeepseekCategorizer()->version())->toBe('deepseek-chat-v1');
});
