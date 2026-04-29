<?php

declare(strict_types=1);

use App\Contracts\AiCategorizerInterface;
use App\Services\AiCategorizers\FakeAiCategorizer;

it('resolves the fake categorizer when AI_DRIVER=fake', function () {
    config()->set('services.ai.driver', 'fake');
    app()->forgetInstance(AiCategorizerInterface::class);

    $instance = app(AiCategorizerInterface::class);

    expect($instance)->toBeInstanceOf(FakeAiCategorizer::class);
});

it('throws a clear error for unknown drivers', function () {
    config()->set('services.ai.driver', 'something-weird');
    app()->forgetInstance(AiCategorizerInterface::class);

    expect(fn () => app(AiCategorizerInterface::class))
        ->toThrow(RuntimeException::class, 'Unknown AI_DRIVER');
});

it('throws explicitly for the not-yet-wired groq driver', function () {
    config()->set('services.ai.driver', 'groq');
    app()->forgetInstance(AiCategorizerInterface::class);

    expect(fn () => app(AiCategorizerInterface::class))
        ->toThrow(RuntimeException::class, 'Etap 4.2');
});
