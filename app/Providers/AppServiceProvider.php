<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AiCategorizerInterface;
use App\Services\AiCategorizers\FakeAiCategorizer;
use App\Services\AiCategorizers\GroqAiCategorizer;
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
                'groq' => $this->makeGroqCategorizer(),
                default => throw new RuntimeException("Unknown AI_DRIVER: {$driver}"),
            };
        });
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
    }

    private function makeGroqCategorizer(): GroqAiCategorizer
    {
        $apiKey = (string) config('services.groq.api_key');
        if ($apiKey === '') {
            throw new RuntimeException(
                'AI_DRIVER=groq but GROQ_API_KEY is empty. Set the key in .env or switch back to AI_DRIVER=fake.',
            );
        }

        return new GroqAiCategorizer(
            apiKey: $apiKey,
            model: (string) config('services.groq.model', 'llama-3.3-70b-versatile'),
            baseUrl: (string) config('services.groq.base_url', 'https://api.groq.com/openai/v1'),
        );
    }
}
