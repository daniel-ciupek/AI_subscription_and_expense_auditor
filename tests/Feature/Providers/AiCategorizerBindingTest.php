<?php

declare(strict_types=1);

use App\Contracts\AiCategorizerInterface;
use App\Services\AiCategorizers\DeepseekAiCategorizer;
use App\Services\AiCategorizers\FakeAiCategorizer;
use App\Services\AiCategorizers\GroqAiCategorizer;

it('resolves the fake categorizer when AI_DRIVER=fake', function () {
    config()->set('services.ai.driver', 'fake');
    app()->forgetInstance(AiCategorizerInterface::class);

    $instance = app(AiCategorizerInterface::class);

    expect($instance)->toBeInstanceOf(FakeAiCategorizer::class);
});

it('resolves the groq categorizer when AI_DRIVER=groq and a key is present', function () {
    config()->set('services.ai.driver', 'groq');
    config()->set('services.groq.api_key', 'gsk_test_123');
    app()->forgetInstance(AiCategorizerInterface::class);

    $instance = app(AiCategorizerInterface::class);

    expect($instance)->toBeInstanceOf(GroqAiCategorizer::class);
});

it('throws a clear error when AI_DRIVER=groq but the key is missing', function () {
    config()->set('services.ai.driver', 'groq');
    config()->set('services.groq.api_key', '');
    app()->forgetInstance(AiCategorizerInterface::class);

    expect(fn () => app(AiCategorizerInterface::class))
        ->toThrow(RuntimeException::class, 'GROQ_API_KEY is empty');
});

it('resolves the deepseek categorizer when AI_DRIVER=deepseek and a key is present', function () {
    config()->set('services.ai.driver', 'deepseek');
    config()->set('services.deepseek.api_key', 'sk-test-123');
    app()->forgetInstance(AiCategorizerInterface::class);

    $instance = app(AiCategorizerInterface::class);

    expect($instance)->toBeInstanceOf(DeepseekAiCategorizer::class);
});

it('throws a clear error when AI_DRIVER=deepseek but the key is missing', function () {
    config()->set('services.ai.driver', 'deepseek');
    config()->set('services.deepseek.api_key', '');
    app()->forgetInstance(AiCategorizerInterface::class);

    expect(fn () => app(AiCategorizerInterface::class))
        ->toThrow(RuntimeException::class, 'DEEPSEEK_API_KEY is empty');
});

it('throws a clear error for unknown drivers', function () {
    config()->set('services.ai.driver', 'something-weird');
    app()->forgetInstance(AiCategorizerInterface::class);

    expect(fn () => app(AiCategorizerInterface::class))
        ->toThrow(RuntimeException::class, 'Unknown AI_DRIVER');
});
