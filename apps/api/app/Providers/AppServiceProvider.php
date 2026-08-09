<?php

namespace App\Providers;

use App\Contracts\CipcVerifier;
use App\Contracts\IssueTracker;
use App\Contracts\VirusScanner;
use App\Services\Ai\AiGateway;
use App\Services\Ai\Drivers\AnthropicAiDriver;
use App\Services\Ai\Drivers\NullAiDriver;
use App\Services\Ai\Drivers\OpenAiDriver;
use App\Services\Business\HttpCipcVerifier;
use App\Services\Business\NullCipcVerifier;
use App\Services\Business\StubCipcVerifier;
use App\Services\Observability\GithubIssueTracker;
use App\Services\Observability\NullIssueTracker;
use App\Services\Payments\NullPaymentDriver;
use App\Services\Payments\PayFastDriver;
use App\Services\Payments\PaymentGateway;
use App\Services\Posts\PostTypeRegistry;
use App\Services\SafetyService;
use App\Services\Security\ClamAvScanner;
use App\Services\Security\NullVirusScanner;
use App\Services\Streaming\MuxStreamDriver;
use App\Services\Streaming\NullStreamDriver;
use App\Services\Streaming\StreamProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        // Virus scanner — ClamAV when enabled, otherwise a no-op scanner so the
        // upload pipeline never hard-depends on a running clamd. Resolved from
        // config at resolution time.
        $this->app->bind(VirusScanner::class, function () {
            if (! config('services.clamav.enabled')) {
                return new NullVirusScanner;
            }

            return new ClamAvScanner(
                host: (string) config('services.clamav.host'),
                port: (int) config('services.clamav.port'),
                timeout: (int) config('services.clamav.timeout'),
                maxBytes: (int) config('services.clamav.max_bytes'),
            );
        });

        // CIPC company-registration verifier — Null when disabled (never grants
        // a sticker without a real provider), a stub for dev/demo, or the HTTP
        // driver for a configured provider. Resolved from config at run time.
        $this->app->bind(CipcVerifier::class, function () {
            if (! config('services.cipc.enabled')) {
                return new NullCipcVerifier;
            }

            return match (config('services.cipc.driver')) {
                'http' => new HttpCipcVerifier(
                    baseUrl: (string) config('services.cipc.base_url'),
                    token: config('services.cipc.token'),
                    timeout: (int) config('services.cipc.timeout'),
                ),
                default => new StubCipcVerifier,
            };
        });

        // Payment gateway (Shop P2) — resolved from config at resolution time so
        // runtime integration settings can switch the provider/credentials.
        $this->app->bind(PaymentGateway::class, function () {
            return match (config('payments.driver')) {
                'payfast' => new PayFastDriver(config('payments.payfast', [])),
                default => new NullPaymentDriver,
            };
        });

        // Live-streaming provider (ask #4) — resolved from config at resolution
        // time so runtime integration settings can switch the provider.
        $this->app->bind(StreamProvider::class, function () {
            return match (config('streaming.driver')) {
                'mux' => new MuxStreamDriver(config('streaming.mux', [])),
                default => new NullStreamDriver,
            };
        });

        // Observability issue tracker — files a de-duplicated triage issue for
        // inbound error alerts. Bound (not singleton) so runtime integration
        // settings can switch the driver/credentials at resolution time.
        $this->app->bind(IssueTracker::class, function () {
            return match (config('observability.driver')) {
                'github' => new GithubIssueTracker(config('observability.github', [])),
                default => new NullIssueTracker,
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

        // File uploads (chunked media, product files, course attachments): a
        // single account could otherwise exhaust the storage volume.
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Money/entitlement writes (checkout, free enrol): cap spam that would
        // create unbounded orders and fan out a webhook per call.
        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(12)->by($request->user()?->id ?: $request->ip());
        });

        $this->warnOnRiskyPaymentConfig();
    }

    /**
     * Surface payment-integrity foot-guns in production: PayFast running in
     * sandbox (test transactions treated as real) or without a passphrase (the
     * MD5 signature is then forgeable and only the ITN postback protects
     * checkout). Log-only — never blocks boot.
     */
    private function warnOnRiskyPaymentConfig(): void
    {
        if (! app()->isProduction() || config('payments.driver') !== 'payfast') {
            return;
        }

        if (config('payments.payfast.sandbox')) {
            Log::warning('PayFast is in SANDBOX mode in production — real checkouts hit the sandbox.');
        }

        if (empty(config('payments.payfast.passphrase'))) {
            Log::warning('PayFast has no passphrase set — the ITN signature is forgeable; set one in Integrations.');
        }
    }
}
