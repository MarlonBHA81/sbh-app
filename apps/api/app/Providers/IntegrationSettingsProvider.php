<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Applies integration settings persisted in the `settings` table on top of the
 * static config files, so a super admin can configure AI and email delivery
 * from the admin panel without editing .env. Overrides are applied during boot,
 * before anything resolves the AI gateway or the mailer.
 *
 * Everything is wrapped in a try/catch and a table-existence check so a fresh
 * install, a pending migration, or a CLI command that runs before the settings
 * table exists never breaks the bootstrap.
 */
class IntegrationSettingsProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $this->applyAiSettings();
            $this->applyMailSettings();
            $this->applyTenderSettings();
        } catch (Throwable) {
            // Missing table / unavailable database during bootstrap: fall back
            // to the config file / env values untouched.
        }
    }

    private function applyAiSettings(): void
    {
        $driver = Setting::get('integrations.ai.driver');
        $model = Setting::get('integrations.ai.model');
        $legacyKey = Setting::get('integrations.ai.api_key');
        $anthropicKey = Setting::get('integrations.ai.anthropic_api_key');
        $openaiKey = Setting::get('integrations.ai.openai_api_key');

        if ($driver !== null && $driver !== '') {
            config(['ai.driver' => $driver]);
        }

        // Model applies to whichever provider is selected (anthropic stays the
        // fallback so pre-OpenAI settings keep working).
        $provider = in_array($driver, ['anthropic', 'openai'], true) ? $driver : 'anthropic';

        // Legacy single key first (applies to the active provider), then the
        // per-provider keys override so a super admin can hold both at once.
        if ($legacyKey !== null && $legacyKey !== '') {
            config(["ai.{$provider}.api_key" => $legacyKey]);
        }

        if ($anthropicKey !== null && $anthropicKey !== '') {
            config(['ai.anthropic.api_key' => $anthropicKey]);
        }

        if ($openaiKey !== null && $openaiKey !== '') {
            config(['ai.openai.api_key' => $openaiKey]);
        }

        if ($model !== null && $model !== '') {
            config(["ai.{$provider}.model" => $model]);
        }
    }

    private function applyTenderSettings(): void
    {
        // Boolean toggle: only override when the row exists (null = untouched).
        $enabled = Setting::get('integrations.tenders.enabled');
        if ($enabled !== null) {
            config(['tenders.enabled' => (bool) $enabled]);
        }

        $map = [
            'integrations.tenders.base_url' => 'tenders.base_url',
            'integrations.tenders.version' => 'tenders.version',
            'integrations.tenders.source' => 'tenders.source',
        ];

        foreach ($map as $settingKey => $configKey) {
            $value = Setting::get($settingKey);

            if ($value !== null && $value !== '') {
                $normalised = $configKey === 'tenders.base_url' ? rtrim((string) $value, '/') : $value;
                config([$configKey => $normalised]);
            }
        }
    }

    private function applyMailSettings(): void
    {
        $mailer = Setting::get('integrations.mail.mailer');

        if ($mailer !== null && $mailer !== '') {
            config(['mail.default' => $mailer]);
        }

        $map = [
            'integrations.mail.host' => 'mail.mailers.smtp.host',
            'integrations.mail.port' => 'mail.mailers.smtp.port',
            'integrations.mail.username' => 'mail.mailers.smtp.username',
            'integrations.mail.password' => 'mail.mailers.smtp.password',
            'integrations.mail.from_address' => 'mail.from.address',
            'integrations.mail.from_name' => 'mail.from.name',
        ];

        foreach ($map as $settingKey => $configKey) {
            $value = Setting::get($settingKey);

            if ($value !== null && $value !== '') {
                config([$configKey => $value]);
            }
        }

        $this->applyEncryption(Setting::get('integrations.mail.encryption'));
    }

    /**
     * Translate the friendly encryption choice into the Symfony SMTP transport
     * settings Laravel expects (scheme + encryption).
     */
    private function applyEncryption(mixed $encryption): void
    {
        if ($encryption === null || $encryption === '') {
            return;
        }

        [$scheme, $enc] = match ($encryption) {
            'ssl' => ['smtps', 'ssl'],
            'tls' => ['smtp', 'tls'],
            default => ['smtp', null], // 'none'
        };

        config([
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.encryption' => $enc,
        ]);
    }
}
