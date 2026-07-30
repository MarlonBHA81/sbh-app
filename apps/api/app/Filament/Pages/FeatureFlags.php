<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Features;
use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Super-admin-only feature switchboard. Each flag from config/features.php is a
 * toggle persisted to the settings table ('features.<key>'); App\Support\Features
 * resolves them for the server gates and the /api/v1/me payload the web client
 * reads. Access is gated to super admins in both navigation and direct URL.
 *
 * @property-read Schema $form
 */
class FeatureFlags extends Page
{
    protected string $view = 'filament.pages.feature-flags';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Feature flags';

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
        $this->form->fill(Features::all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->sections())
            ->statePath('data');
    }

    /**
     * One Section per flag group, each holding that group's toggles — driven by
     * the config/features.php registry so a new flag needs no page edit.
     *
     * @return list<Section>
     */
    private function sections(): array
    {
        $groups = [];

        foreach (Features::registry() as $key => $meta) {
            $group = (string) ($meta['group'] ?? 'General');

            $groups[$group][] = Toggle::make($key)
                ->label((string) ($meta['label'] ?? $key))
                ->helperText((string) ($meta['description'] ?? ''));
        }

        $sections = [];

        foreach ($groups as $group => $toggles) {
            $sections[] = Section::make($group)->schema($toggles)->columns(1);
        }

        return $sections;
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (array_keys(Features::registry()) as $key) {
            Setting::set('features.'.$key, (bool) ($data[$key] ?? false));
        }

        Notification::make()->title('Feature flags saved')->success()->send();
    }
}
