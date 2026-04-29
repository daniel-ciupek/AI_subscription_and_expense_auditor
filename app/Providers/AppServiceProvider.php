<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AiCategorizerInterface;
use App\Services\AiCategorizers\FakeAiCategorizer;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiCategorizerInterface::class, function (): AiCategorizerInterface {
            $driver = (string) config('services.ai.driver', 'fake');

            return match ($driver) {
                'fake' => new FakeAiCategorizer,
                'groq' => throw new RuntimeException(
                    'GroqAiCategorizer not yet wired (Etap 4.2). Set AI_DRIVER=fake.',
                ),
                default => throw new RuntimeException("Unknown AI_DRIVER: {$driver}"),
            };
        });
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }
}
