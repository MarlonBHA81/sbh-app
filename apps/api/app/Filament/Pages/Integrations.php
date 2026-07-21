<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Throwable;
use UnitEnum;

/**
 * Super-admin-only integration settings. Persists AI and email configuration to
 * the settings table, which IntegrationSettingsProvider layers over config at
 * boot. Access is gated to super admins in both navigation and direct URL.
 *
 * @property-read Schema $form
 */
class Integrations extends Page
{
    protected string $view = 'filament.pages.integrations';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Integrations';

    /**
     * Selectable models per provider, cheapest first. Extend as new models ship.
     *
     * @var array<string, array<string, string>>
     */
    public const MODELS = [
        'anthropic' => [
            'claude-haiku-4-5-20251001' => 'Haiku 4.5 — fastest & cheapest (recommended)',
            'claude-sonnet-5' => 'Sonnet 5 — balanced',
            'claude-opus-4-8' => 'Opus 4.8 — most capable',
            'claude-fable-5' => 'Fable 5',
        ],
        'openai' => [
            'gpt-4o-mini' => 'GPT-4o mini — cheapest',
            'gpt-4o' => 'GPT-4o',
        ],
    ];

    /** @var array<string, mixed> */
    public ?array $data = [];

    /**
     * Model choices for the selected driver (anthropic is the fallback so the
     * dropdown is never empty when the provider is Disabled).
     *
     * @return array<string, string>
     */
    public static function modelsFor(?string $driver): array
    {
        return self::MODELS[$driver] ?? self::MODELS['anthropic'];
    }

    /**
     * Gate both navigation visibility and direct-URL access to super admins.
     * Filament aborts 403 when canAccess() returns false.
     */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    public function mount(): void
    {
        $this->form->fill([
            'ai_driver' => (string) Setting::get('integrations.ai.driver', 'null'),
            // Per-provider keys, falling back to the legacy single key.
            'ai_anthropic_api_key' => (string) Setting::get('integrations.ai.anthropic_api_key', Setting::get('integrations.ai.api_key', '')),
            'ai_openai_api_key' => (string) Setting::get('integrations.ai.openai_api_key', ''),
            'ai_model' => (string) Setting::get('integrations.ai.model', 'claude-haiku-4-5-20251001'),

            'mail_mailer' => (string) Setting::get('integrations.mail.mailer', 'log'),
            'mail_host' => (string) Setting::get('integrations.mail.host', ''),
            'mail_port' => (string) Setting::get('integrations.mail.port', ''),
            'mail_encryption' => (string) Setting::get('integrations.mail.encryption', 'tls'),
            'mail_username' => (string) Setting::get('integrations.mail.username', ''),
            'mail_password' => (string) Setting::get('integrations.mail.password', ''),
            'mail_from_address' => (string) Setting::get('integrations.mail.from_address', ''),
            'mail_from_name' => (string) Setting::get('integrations.mail.from_name', ''),

            'tenders_enabled' => (bool) Setting::get('integrations.tenders.enabled', config('tenders.enabled')),
            'tenders_base_url' => (string) Setting::get('integrations.tenders.base_url', config('tenders.base_url')),
            'tenders_version' => (string) Setting::get('integrations.tenders.version', config('tenders.version')),
            'tenders_source' => (string) Setting::get('integrations.tenders.source', config('tenders.source')),

            // env('PAYMENTS_DRIVER=null') parses to PHP null, so coalesce any
            // empty value back to the 'null' option key (the required Select).
            'payments_driver' => Setting::get('integrations.payments.driver', config('payments.driver')) ?: 'null',
            'payments_platform_fee_percent' => (string) Setting::get('integrations.payments.platform_fee_percent', (string) config('payments.platform_fee_percent', 10)),
            'payfast_sandbox' => (bool) Setting::get('integrations.payments.payfast.sandbox', config('payments.payfast.sandbox', true)),
            'payfast_merchant_id' => (string) Setting::get('integrations.payments.payfast.merchant_id', ''),
            'payfast_merchant_key' => (string) Setting::get('integrations.payments.payfast.merchant_key', ''),
            'payfast_passphrase' => (string) Setting::get('integrations.payments.payfast.passphrase', ''),

            'streaming_driver' => Setting::get('integrations.streaming.driver', config('streaming.driver')) ?: 'null',
            'mux_token_id' => (string) Setting::get('integrations.streaming.mux.token_id', ''),
            'mux_token_secret' => (string) Setting::get('integrations.streaming.mux.token_secret', ''),
            'mux_webhook_secret' => (string) Setting::get('integrations.streaming.mux.webhook_secret', ''),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('AI')
                    ->description('Powers composer topic suggestions and AI moderation assist on reports.')
                    ->schema([
                        Select::make('ai_driver')
                            ->label('AI provider')
                            ->options([
                                'null' => 'Disabled',
                                'anthropic' => 'Anthropic (Claude)',
                                'openai' => 'OpenAI (GPT)',
                            ])
                            ->default('null')
                            ->required()
                            ->live()
                            // Switching provider resets the model to that provider's
                            // cheapest option so an Anthropic id can't leak to OpenAI.
                            ->afterStateUpdated(fn (?string $state, Set $set) => $set(
                                'ai_model',
                                (string) array_key_first(self::modelsFor($state)),
                            ))
                            ->helperText('Enables composer topic suggestions, AI moderation assist, the Coach and the Daily Brief.'),
                        Select::make('ai_model')
                            ->label('Model')
                            ->options(fn (Get $get): array => self::modelsFor($get('ai_driver')))
                            ->default('claude-haiku-4-5-20251001')
                            ->searchable()
                            ->native(false)
                            ->helperText('All available models. Haiku 4.5 is the cheapest and fastest — a good default.'),
                        TextInput::make('ai_anthropic_api_key')
                            ->label('Anthropic API key')
                            ->password()
                            ->revealable()
                            ->autocomplete(false)
                            ->helperText('Used when the provider is Anthropic (Claude).'),
                        TextInput::make('ai_openai_api_key')
                            ->label('OpenAI API key')
                            ->password()
                            ->revealable()
                            ->autocomplete(false)
                            ->helperText('Used when the provider is OpenAI (GPT).'),
                    ]),

                Section::make('Email')
                    ->description('Outbound email delivery. Log-only keeps mail in the application log.')
                    ->schema([
                        Select::make('mail_mailer')
                            ->label('Mailer')
                            ->options([
                                'log' => 'Log only',
                                'smtp' => 'SMTP',
                            ])
                            ->default('log')
                            ->required(),
                        TextInput::make('mail_host')
                            ->label('Host')
                            ->helperText('Brevo: smtp-relay.brevo.com, port 587, TLS, username = your Brevo account email, password = your Brevo SMTP key'),
                        TextInput::make('mail_port')
                            ->label('Port')
                            ->numeric()
                            ->helperText('Resend: smtp.resend.com, port 465, SSL, username = resend, password = your Resend API key'),
                        Select::make('mail_encryption')
                            ->label('Encryption')
                            ->options([
                                'tls' => 'TLS',
                                'ssl' => 'SSL',
                                'none' => 'None',
                            ])
                            ->default('tls'),
                        TextInput::make('mail_username')
                            ->label('Username')
                            ->autocomplete(false),
                        TextInput::make('mail_password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->autocomplete(false),
                        TextInput::make('mail_from_address')
                            ->label('From address')
                            ->email(),
                        TextInput::make('mail_from_name')
                            ->label('From name'),
                    ]),

                Section::make('Tenders (OpenProcurement)')
                    ->description('Pull open tenders into the Opportunities feed from an OpenProcurement-compatible API. Runs hourly when enabled.')
                    ->schema([
                        Toggle::make('tenders_enabled')
                            ->label('Enable tender sync')
                            ->helperText('Off leaves the hourly tenders:sync job a no-op.'),
                        TextInput::make('tenders_base_url')
                            ->label('Base URL')
                            ->url()
                            ->helperText('Point at a South-African OpenProcurement endpoint for SA tenders. Default serves the public standard data.'),
                        TextInput::make('tenders_version')
                            ->label('API version')
                            ->helperText('e.g. 2.5'),
                        TextInput::make('tenders_source')
                            ->label('Source label')
                            ->helperText('Stamped on imported opportunities and used to de-duplicate (e.g. "eTenders", "OpenProcurement").'),
                    ]),

                Section::make('Payments (PayFast)')
                    ->description('Marketplace checkout. Buyers pay via PayFast; orders are confirmed by PayFast\'s ITN. Leave the provider Disabled to hide checkout.')
                    ->schema([
                        Select::make('payments_driver')
                            ->label('Payment provider')
                            ->options([
                                'null' => 'Disabled',
                                'payfast' => 'PayFast',
                            ])
                            ->default('null')
                            ->required()
                            ->helperText('Disabled hides the Buy button and rejects checkout.'),
                        Toggle::make('payfast_sandbox')
                            ->label('Sandbox mode')
                            ->helperText('On uses PayFast\'s sandbox (sandbox.payfast.co.za) for testing. Turn off for live payments.'),
                        TextInput::make('payments_platform_fee_percent')
                            ->label('Platform fee (%)')
                            ->numeric()
                            ->helperText('Commission kept from each paid order. The rest is recorded as the vendor\'s earnings.'),
                        TextInput::make('payfast_merchant_id')
                            ->label('Merchant ID')
                            ->autocomplete(false)
                            ->helperText('From your PayFast dashboard. Sandbox test ID: 10000100.'),
                        TextInput::make('payfast_merchant_key')
                            ->label('Merchant key')
                            ->password()
                            ->revealable()
                            ->autocomplete(false)
                            ->helperText('Sandbox test key: 46f0cd694581a.'),
                        TextInput::make('payfast_passphrase')
                            ->label('Passphrase')
                            ->password()
                            ->revealable()
                            ->autocomplete(false)
                            ->helperText('Optional, but set it in PayFast and here to sign requests. Required once your account has a passphrase configured.'),
                    ]),

                Section::make('Live streaming (Mux)')
                    ->description('Host masterclass rooms live. The host streams via RTMP to Mux; members watch the HLS playback in the app.')
                    ->schema([
                        Select::make('streaming_driver')
                            ->label('Streaming provider')
                            ->options([
                                'null' => 'Disabled',
                                'mux' => 'Mux',
                            ])
                            ->default('null')
                            ->required()
                            ->helperText('Disabled hides the "Go live" controls.'),
                        TextInput::make('mux_token_id')
                            ->label('Mux access token ID')
                            ->autocomplete(false),
                        TextInput::make('mux_token_secret')
                            ->label('Mux secret key')
                            ->password()
                            ->revealable()
                            ->autocomplete(false),
                        TextInput::make('mux_webhook_secret')
                            ->label('Mux webhook signing secret')
                            ->password()
                            ->revealable()
                            ->autocomplete(false)
                            ->helperText('From the Mux webhook settings. Point the webhook at /api/v1/streaming/webhook.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('integrations.ai.driver', (string) ($data['ai_driver'] ?? 'null'));
        Setting::set('integrations.ai.model', (string) ($data['ai_model'] ?? ''));
        // Per-provider keys — each editable independently (add or change either).
        Setting::set('integrations.ai.anthropic_api_key', (string) ($data['ai_anthropic_api_key'] ?? ''));
        Setting::set('integrations.ai.openai_api_key', (string) ($data['ai_openai_api_key'] ?? ''));

        Setting::set('integrations.mail.mailer', (string) ($data['mail_mailer'] ?? 'log'));
        Setting::set('integrations.mail.host', (string) ($data['mail_host'] ?? ''));
        Setting::set('integrations.mail.port', (string) ($data['mail_port'] ?? ''));
        Setting::set('integrations.mail.encryption', (string) ($data['mail_encryption'] ?? 'tls'));
        Setting::set('integrations.mail.username', (string) ($data['mail_username'] ?? ''));
        Setting::set('integrations.mail.password', (string) ($data['mail_password'] ?? ''));
        Setting::set('integrations.mail.from_address', (string) ($data['mail_from_address'] ?? ''));
        Setting::set('integrations.mail.from_name', (string) ($data['mail_from_name'] ?? ''));

        Setting::set('integrations.tenders.enabled', (bool) ($data['tenders_enabled'] ?? false));
        Setting::set('integrations.tenders.base_url', (string) ($data['tenders_base_url'] ?? ''));
        Setting::set('integrations.tenders.version', (string) ($data['tenders_version'] ?? ''));
        Setting::set('integrations.tenders.source', (string) ($data['tenders_source'] ?? ''));

        Setting::set('integrations.payments.driver', (string) ($data['payments_driver'] ?? 'null'));
        Setting::set('integrations.payments.platform_fee_percent', (string) ($data['payments_platform_fee_percent'] ?? '10'));
        Setting::set('integrations.payments.payfast.sandbox', (bool) ($data['payfast_sandbox'] ?? true));
        Setting::set('integrations.payments.payfast.merchant_id', (string) ($data['payfast_merchant_id'] ?? ''));
        Setting::set('integrations.payments.payfast.merchant_key', (string) ($data['payfast_merchant_key'] ?? ''));
        Setting::set('integrations.payments.payfast.passphrase', (string) ($data['payfast_passphrase'] ?? ''));

        Setting::set('integrations.streaming.driver', (string) ($data['streaming_driver'] ?? 'null'));
        Setting::set('integrations.streaming.mux.token_id', (string) ($data['mux_token_id'] ?? ''));
        Setting::set('integrations.streaming.mux.token_secret', (string) ($data['mux_token_secret'] ?? ''));
        Setting::set('integrations.streaming.mux.webhook_secret', (string) ($data['mux_webhook_secret'] ?? ''));

        Notification::make()->title('Integration settings saved')->success()->send();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->action('save'),

            Action::make('sendTestEmail')
                ->label('Send test email')
                ->color('gray')
                ->action(function (): void {
                    // Persist first so the current form values take effect via
                    // the config-override provider on the next mailer resolve.
                    $this->save();

                    $recipient = (string) auth()->user()?->email;

                    try {
                        Mail::raw('This is a test email from the Story admin panel.', function ($message) use ($recipient) {
                            $message->to($recipient)->subject('Story test email');
                        });

                        Notification::make()
                            ->title("Test email sent to {$recipient}")
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Test email failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
