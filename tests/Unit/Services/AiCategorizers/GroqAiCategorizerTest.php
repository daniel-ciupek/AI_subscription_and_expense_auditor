<?php

declare(strict_types=1);

use App\DTOs\TransactionForCategorization;
use App\Services\AiCategorizers\GroqAiCategorizer;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

function makeGroqCategorizer(): GroqAiCategorizer
{
    return new GroqAiCategorizer(
        apiKey: 'gsk_test_123',
        model: 'llama-3.3-70b-versatile',
        baseUrl: 'https://api.groq.com/openai/v1',
        timeoutSeconds: 5,
    );
}

function fakeGroqResponse(array $categorizations, int $status = 200): array
{
    return [
        'api.groq.com/*' => Http::response([
            'choices' => [[
                'message' => [
                    'content' => json_encode(['categorizations' => $categorizations]),
                ],
            ]],
        ], $status),
    ];
}

it('returns an empty array when given no transactions', function () {
    Http::fake();

    expect(makeGroqCategorizer()->categorize([]))->toBe([]);

    Http::assertNothingSent();
});

it('parses a successful Groq response into AiCategorization DTOs', function () {
    Http::fake(fakeGroqResponse([
        ['id' => 1, 'category_slug' => 'subscriptions', 'confidence' => 0.96],
        ['id' => 2, 'category_slug' => 'food', 'confidence' => 0.88],
    ]));

    $results = makeGroqCategorizer()->categorize([
        new TransactionForCategorization(1, 'NETFLIX SUBSCRIPTION', '-49.99'),
        new TransactionForCategorization(2, 'BIEDRONKA', '-87.40'),
    ]);

    expect($results)->toHaveCount(2);
    expect($results[1]->categorySlug)->toBe('subscriptions');
    expect($results[1]->confidence)->toBe(0.96);
    expect($results[2]->categorySlug)->toBe('food');
});

it('sends Bearer auth and json_object response_format', function () {
    Http::fake(fakeGroqResponse([
        ['id' => 1, 'category_slug' => 'food', 'confidence' => 0.9],
    ]));

    makeGroqCategorizer()->categorize([
        new TransactionForCategorization(1, 'BIEDRONKA', '-50.00'),
    ]);

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer gsk_test_123')
            && data_get($request->data(), 'response_format.type') === 'json_object'
            && data_get($request->data(), 'model') === 'llama-3.3-70b-versatile';
    });
});

it('falls back to "other" for transactions missing in the response', function () {
    Http::fake(fakeGroqResponse([
        ['id' => 1, 'category_slug' => 'subscriptions', 'confidence' => 0.99],
        // id 2 omitted on purpose
    ]));

    $results = makeGroqCategorizer()->categorize([
        new TransactionForCategorization(1, 'NETFLIX', '-49.99'),
        new TransactionForCategorization(2, 'WHATEVER', '-10.00'),
    ]);

    expect($results[2]->categorySlug)->toBe('other');
    expect($results[2]->confidence)->toBe(0.0);
    expect($results[2]->rawResponse)
        ->toHaveKey('fallback', true)
        ->toHaveKey('reason', 'missing_in_response');
});

it('rejects unknown category slugs and falls back to "other" for the whole batch', function () {
    Http::fake(fakeGroqResponse([
        ['id' => 1, 'category_slug' => 'cryptocurrencies', 'confidence' => 0.99],
    ]));

    $results = makeGroqCategorizer()->categorize([
        new TransactionForCategorization(1, 'BTC', '-100.00'),
    ]);

    expect($results[1]->categorySlug)->toBe('other');
    expect($results[1]->rawResponse)->toHaveKey('error', 'schema_violation');
});

it('falls back when the model returns invalid JSON', function () {
    Http::fake([
        'api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'not json at all']]],
        ], 200),
    ]);

    $results = makeGroqCategorizer()->categorize([
        new TransactionForCategorization(1, 'X', '-1.00'),
    ]);

    expect($results[1]->categorySlug)->toBe('other');
    expect($results[1]->rawResponse)->toHaveKey('error', 'invalid_json');
});

it('retries 5xx responses with exponential backoff and falls back if they all fail', function () {
    Http::fake([
        'api.groq.com/*' => Http::sequence()
            ->push('upstream timeout', 503)
            ->push('upstream timeout', 503)
            ->push('upstream timeout', 503)
            ->push('upstream timeout', 503),
    ]);

    $results = makeGroqCategorizer()->categorize([
        new TransactionForCategorization(1, 'NETFLIX', '-49.99'),
    ]);

    expect($results[1]->categorySlug)->toBe('other');
    expect($results[1]->rawResponse)->toHaveKey('error', 'http_503');
});

it('reports the configured prompt version', function () {
    expect(makeGroqCategorizer()->version())->toBe('groq-llama-3.3-70b-v1');
});
