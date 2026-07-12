<?php

use App\Models\Setting;
use App\Providers\IntegrationSettingsProvider;
use App\Services\Ai\AiGateway;
use App\Services\Ai\Drivers\AnthropicAiDriver;
use App\Services\Ai\Drivers\NullAiDriver;

/**
 * Re-run the integration settings provider's boot logic against the current
 * config repository, the same way it runs during application bootstrap.
 */
function applyIntegrationSettings(): void
{
    (new IntegrationSettingsProvider(app()))->boot();
}

test('ai integration settings override the ai config at runtime', function () {
    Setting::set('integrations.ai.driver', 'anthropic');
    Setting::set('integrations.ai.api_key', 'sk-test-123');
    Setting::set('integrations.ai.model', 'claude-sonnet-4-5-20250101');

    applyIntegrationSettings();

    expect(config('ai.driver'))->toBe('anthropic')
        ->and(config('ai.anthropic.api_key'))->toBe('sk-test-123')
        ->and(config('ai.anthropic.model'))->toBe('claude-sonnet-4-5-20250101');
});

test('mail integration settings override the mail config at runtime', function () {
    Setting::set('integrations.mail.mailer', 'smtp');
    Setting::set('integrations.mail.host', 'smtp-relay.brevo.com');
    Setting::set('integrations.mail.port', '587');
    Setting::set('integrations.mail.encryption', 'tls');
    Setting::set('integrations.mail.username', 'me@example.com');
    Setting::set('integrations.mail.password', 'secret-key');
    Setting::set('integrations.mail.from_address', 'noreply@example.com');
    Setting::set('integrations.mail.from_name', 'Story');

    applyIntegrationSettings();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp-relay.brevo.com')
        ->and(config('mail.mailers.smtp.port'))->toBe('587')
        ->and(config('mail.mailers.smtp.username'))->toBe('me@example.com')
        ->and(config('mail.mailers.smtp.password'))->toBe('secret-key')
        ->and(config('mail.from.address'))->toBe('noreply@example.com')
        ->and(config('mail.from.name'))->toBe('Story');
});

test('ssl encryption maps to the smtps scheme', function () {
    Setting::set('integrations.mail.encryption', 'ssl');

    applyIntegrationSettings();

    expect(config('mail.mailers.smtp.scheme'))->toBe('smtps')
        ->and(config('mail.mailers.smtp.encryption'))->toBe('ssl');
});

test('the provider tolerates absent integration settings', function () {
    // No settings rows at all: config is left at its defaults, no exception.
    applyIntegrationSettings();

    expect(config('ai.driver'))->not->toBeNull();
});

test('the ai gateway resolves the driver from config at resolution time', function () {
    config(['ai.driver' => 'null']);
    expect(app(AiGateway::class))->toBeInstanceOf(NullAiDriver::class);

    // Persisting anthropic settings and re-applying the overrides switches the
    // driver a fresh resolve returns.
    Setting::set('integrations.ai.driver', 'anthropic');
    Setting::set('integrations.ai.api_key', 'sk-live-abc');
    applyIntegrationSettings();

    $gateway = app(AiGateway::class);

    expect($gateway)->toBeInstanceOf(AnthropicAiDriver::class)
        ->and($gateway->enabled())->toBeTrue();
});
