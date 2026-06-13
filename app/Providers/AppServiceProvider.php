<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\WhatsApp\WhatsAppProviderFactory::class);
        $this->app->scoped(\App\Ai\Support\ToolCallTracker::class);
        $this->app->scoped(\App\Services\AgentInteractionContext::class);
        $this->app->bind(\App\Contracts\AgentServiceInterface::class, \App\Services\AgentService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->isProduction()) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        $this->configureDefaults();
        $this->configureRateLimiters();
        $this->validateRequiredEnv();
    }

    /**
     * Per-route rate limiters for webhook + agent endpoints.
     * Keyed per source identifier (instance / phone / IP) so a single noisy
     * tenant cannot starve the rest.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('meta-webhook', function (Request $request): Limit {
            $phoneId = (string) $request->input('entry.0.changes.0.value.metadata.phone_number_id', $request->ip() ?? 'unknown');

            return Limit::perMinute(600)->by("meta:{$phoneId}");
        });

        RateLimiter::for('aria-direct', function (Request $request): Limit {
            $key = (string) ($request->header('X-API-Key') ?? $request->ip() ?? 'unknown');

            return Limit::perMinute((int) config('credflow.api.rate_limit', 120))->by("aria:{$key}");
        });

        RateLimiter::for('tenaz-direct', function (Request $request): Limit {
            $key = (string) ($request->header('X-API-Key') ?? $request->ip() ?? 'unknown');

            return Limit::perMinute((int) config('credflow.api.rate_limit', 120))->by("tenaz:{$key}");
        });

        RateLimiter::for('ura-inbound', function (Request $request): Limit {
            $key = (string) ($request->header('X-API-Key') ?? $request->ip() ?? 'unknown');

            return Limit::perMinute(300)->by("ura:{$key}");
        });

        // Conservative rate limit for the AI auto-tagging queue.
        // Prevents LLM cost blow-up from bursts while allowing steady throughput.
        RateLimiter::for('auto-tags', fn (): Limit => Limit::perMinute(10));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Validate that required environment variables are present.
     * Skipped during testing to avoid false positives.
     */
    protected function validateRequiredEnv(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        $required = [
            'APP_KEY', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
        ];

        // At least one AI provider key must be set
        if (empty(env('OPENROUTER_API_KEY')) && empty(env('OPENAI_API_KEY'))) {
            throw new \RuntimeException(
                'Production requires at least one AI provider key: OPENROUTER_API_KEY or OPENAI_API_KEY'
            );
        }

        $missing = array_filter($required, fn (string $key): bool => empty(env($key)));

        if (! empty($missing)) {
            throw new \RuntimeException(
                'Missing required environment variables: '.implode(', ', $missing)
            );
        }
    }
}
