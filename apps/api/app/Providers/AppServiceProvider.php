<?php

namespace App\Providers;

use App\Services\Ai\AiGateway;
use App\Services\Ai\Drivers\AnthropicAiDriver;
use App\Services\Ai\Drivers\OpenAiDriver;
use App\Services\Ai\Drivers\NullAiDriver;
use App\Services\Posts\PostTypeRegistry;
use App\Services\SafetyService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PostTypeRegistry::class);
        $this->app->singleton(SafetyService::class);

        // Bound (not a singleton) so the driver is resolved from config at
        // resolution time. Integration settings stored in the database can
        // override config('ai.*') at runtime (see IntegrationSettingsProvider),
        // and a fresh resolve must reflect the current driver/credentials.
        $this->app->bind(AiGateway::class, function () {
            return match (config('ai.driver')) {
                'anthropic' => new AnthropicAiDriver(config('ai.anthropic', [])),
                'openai' => new OpenAiDriver(config('ai.openai', [])),
                default => new NullAiDriver,
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('engagement', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('comments', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('messages', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('reports', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('suggest-topics', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('coach', function (Request $request) {
            return Limit::perMinute(15)->by($request->user()?->id ?: $request->ip());
        });
    }
}
