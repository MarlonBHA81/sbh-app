<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    protected string $view = 'filament.pages.manage-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Global settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'registration_open' => (bool) Setting::get('registration_open', true),
            'maintenance_message' => (string) Setting::get('maintenance_message', ''),
            'sensitive_default_blur' => (bool) Setting::get('sensitive_default_blur', true),
            'max_business_profiles' => (int) Setting::get('max_business_profiles', 3),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Toggle::make('registration_open')
                    ->label('Registration open')
                    ->helperText('When off, the register endpoint returns 403.'),
                TextInput::make('maintenance_message')
                    ->label('Maintenance message')
                    ->helperText('Non-empty value is surfaced on the public /status endpoint.')
                    ->maxLength(500),
                Toggle::make('sensitive_default_blur')
                    ->label('Blur sensitive media by default'),
                TextInput::make('max_business_profiles')
                    ->label('Max business profiles per user')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('registration_open', (bool) ($data['registration_open'] ?? true));
        Setting::set('maintenance_message', (string) ($data['maintenance_message'] ?? ''));
        Setting::set('sensitive_default_blur', (bool) ($data['sensitive_default_blur'] ?? true));
        Setting::set('max_business_profiles', (int) ($data['max_business_profiles'] ?? 3));

        Notification::make()->title('Settings saved')->success()->send();
    }
}
