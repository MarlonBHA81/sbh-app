<?php

namespace App\Filament\Pages;

use App\Models\Campaign;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Post;
use App\Models\Profile;
use App\Models\User;
use App\Services\Admin\MasterResetService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use UnitEnum;

/**
 * Super-admin-only tooling to load the demo dataset and to perform the
 * irreversible master reset. Access is gated to super admins in both
 * navigation and direct URL, mirroring the Integrations page pattern.
 */
class DemoReset extends Page
{
    protected string $view = 'filament.pages.demo-reset';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Demo & Reset';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    public function demoUsersExist(): bool
    {
        return User::query()->where('email', 'like', '%@demo.sbh')->exists();
    }

    public function loadDemoAction(): Action
    {
        return Action::make('loadDemo')
            ->label('Load demo content')
            ->icon(Heroicon::OutlinedSparkles)
            ->disabled(fn (): bool => $this->demoUsersExist())
            ->tooltip(fn (): ?string => $this->demoUsersExist()
                ? 'Demo content is already loaded — use "Reseed fresh" to wipe and reload it.'
                : null)
            ->action(function (): void {
                $this->runDemoSeed(fresh: false);
            });
    }

    public function reseedDemoAction(): Action
    {
        return Action::make('reseedDemo')
            ->label('Reseed fresh')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->visible(fn (): bool => $this->demoUsersExist())
            ->requiresConfirmation()
            ->modalHeading('Reseed demo content?')
            ->modalDescription('All existing demo users (*@demo.sbh) and everything they created will be deleted, then a fresh demo dataset will be seeded. Real users are not touched.')
            ->modalSubmitActionLabel('Wipe & reseed')
            ->action(function (): void {
                $this->runDemoSeed(fresh: true);
            });
    }

    public function masterResetAction(): Action
    {
        return Action::make('masterReset')
            ->label('Master reset — delete all content')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('danger')
            ->modalHeading('Master reset — this cannot be undone')
            ->modalDescription('Every non-admin user and ALL content (posts, comments, messages, media, campaigns, reports, XP history) will be permanently deleted. Admin accounts, topics, badges, ranks, business categories, settings and ad slots are kept. Type RESET to confirm.')
            ->modalSubmitActionLabel('Permanently reset')
            ->form([
                TextInput::make('confirmation')
                    ->label('Type RESET to confirm')
                    ->placeholder('RESET')
                    ->required()
                    ->rules(['in:RESET'])
                    ->validationMessages([
                        'in' => 'You must type RESET (all caps) to confirm.',
                    ]),
            ])
            ->action(function (array $data): void {
                if (($data['confirmation'] ?? null) !== 'RESET') {
                    Notification::make()
                        ->title('Reset aborted')
                        ->body('Confirmation text did not match.')
                        ->danger()
                        ->send();

                    return;
                }

                set_time_limit(600);

                $counts = app(MasterResetService::class)->run();

                $deleted = array_filter($counts);
                arsort($deleted);

                $summary = collect($deleted)
                    ->take(8)
                    ->map(fn (int $count, string $table): string => str_replace('_', ' ', $table).": {$count}")
                    ->implode(', ');

                Notification::make()
                    ->title('Master reset complete')
                    ->body(sprintf(
                        '%d rows deleted across %d tables. %s. Admins, topics and settings were kept.',
                        array_sum($counts),
                        count($deleted),
                        $summary !== '' ? 'Largest: '.$summary : 'Nothing to delete',
                    ))
                    ->success()
                    ->persistent()
                    ->send();
            });
    }

    private function runDemoSeed(bool $fresh): void
    {
        set_time_limit(600);

        Artisan::call('demo:seed', array_filter([
            '--force' => true,
            '--fresh' => $fresh,
        ]));

        $demoUserIds = User::query()->where('email', 'like', '%@demo.sbh')->pluck('id');
        $profileIds = Profile::query()->whereIn('user_id', $demoUserIds)->pluck('id');

        Notification::make()
            ->title($fresh ? 'Demo content reseeded' : 'Demo content loaded')
            ->body(sprintf(
                '%d demo users, %d profiles, %d posts, %d comments, %d conversations, %d campaigns. All demo users log in with password "password".',
                $demoUserIds->count(),
                $profileIds->count(),
                Post::query()->whereIn('profile_id', $profileIds)->count(),
                Comment::query()->whereIn('profile_id', $profileIds)->count(),
                Conversation::query()->whereIn('created_by_profile_id', $profileIds)->count(),
                Campaign::query()->whereIn('profile_id', $profileIds)->count(),
            ))
            ->success()
            ->persistent()
            ->send();
    }
}
