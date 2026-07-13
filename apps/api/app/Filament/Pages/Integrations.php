<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
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

    /** @var array<string, mixed> */
    public ?array $data = [];

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
            'ai_api_key' => (string) Setting::get('integrations.ai.api_key', ''),
            'ai_model' => (string) Setting::get('integrations.ai.model', 'claude-haiku-4-5-20251001'),

            'mail_mailer' => (string) Setting::get('integrations.mail.mailer', 'log'),
            'mail_host' => (string) Setting::get('integrations.mail.host', ''),
            'mail_port' => (string) Setting::get('integrations.mail.port', ''),
            'mail_encryption' => (string) Setting::get('integrations.mail.encryption', 'tls'),
            'mail_username' => (string) Setting::get('integrations.mail.username', ''),
            'mail_password' => (string) Setting::get('integrations.mail.password', ''),
            'mail_from_address' => (string) Setting::get('integrations.mail.from_address', ''),
            'mail_from_name' => (string) Setting::get('integrations.mail.from_name', ''),
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
                            ->helperText('Enables composer topic suggestions and AI moderation assist on reports.'),
                        TextInput::make('ai_api_key')
                            ->label('API key')
                            ->password()
                            ->revealable()
                            ->autocomplete(false),
                        TextInput::make('ai_model')
                            ->label('Model')
                            ->default('claude-haiku-4-5-20251001')
                            ->helperText('e.g. claude-haiku-4-5-20251001 (Anthropic) or gpt-4o-mini (OpenAI).'),
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
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('integrations.ai.driver', (string) ($data['ai_driver'] ?? 'null'));
        Setting::set('integrations.ai.api_key', (string) ($data['ai_api_key'] ?? ''));
        Setting::set('integrations.ai.model', (string) ($data['ai_model'] ?? ''));

        Setting::set('integrations.mail.mailer', (string) ($data['mail_mailer'] ?? 'log'));
        Setting::set('integrations.mail.host', (string) ($data['mail_host'] ?? ''));
        Setting::set('integrations.mail.port', (string) ($data['mail_port'] ?? ''));
        Setting::set('integrations.mail.encryption', (string) ($data['mail_encryption'] ?? 'tls'));
        Setting::set('integrations.mail.username', (string) ($data['mail_username'] ?? ''));
        Setting::set('integrations.mail.password', (string) ($data['mail_password'] ?? ''));
        Setting::set('integrations.mail.from_address', (string) ($data['mail_from_address'] ?? ''));
        Setting::set('integrations.mail.from_name', (string) ($data['mail_from_name'] ?? ''));

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
