<?php

namespace App\Filament\Resources\Profiles\Tables;

use App\Models\Badge;
use App\Models\Profile;
use App\Support\Moderation;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('handle')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('kind')->badge()->sortable(),
                TextColumn::make('user.email')->label('Owner')->searchable()->toggleable(),
                TextColumn::make('followers_count')->label('Followers')->sortable(),
                IconColumn::make('is_verified')->boolean()->label('Verified')->sortable(),
                IconColumn::make('is_private')->boolean()->label('Private')->sortable(),
            ])
            ->filters([
                SelectFilter::make('kind')->options([
                    Profile::KIND_PERSONAL => 'Personal',
                    Profile::KIND_BUSINESS => 'Business',
                ]),
                TernaryFilter::make('is_verified')->label('Verified'),
                TernaryFilter::make('is_private')->label('Private'),
            ])
            ->recordActions([
                Action::make('toggle_verified')
                    ->label(fn (Profile $record) => $record->is_verified ? 'Unverify' : 'Verify')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color(fn (Profile $record) => $record->is_verified ? 'gray' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Profile $record): void {
                        $verify = ! $record->is_verified;
                        $record->forceFill(['is_verified' => $verify])->save();

                        $badge = Badge::query()->where('key', 'verified')->first();

                        if ($badge) {
                            if ($verify) {
                                $record->badges()->syncWithoutDetaching([
                                    $badge->id => ['awarded_at' => now()],
                                ]);
                            } else {
                                $record->badges()->detach($badge->id);
                            }
                        }

                        Moderation::log($verify ? 'profile.verify' : 'profile.unverify', $record);

                        Notification::make()->title('Verification updated')->success()->send();
                    }),

                Action::make('edit_meta')
                    ->label('Category & badges')
                    ->icon(Heroicon::OutlinedTag)
                    ->fillForm(fn (Profile $record) => [
                        'category' => $record->category,
                        'badges' => $record->badges()->pluck('badges.id')->all(),
                    ])
                    ->schema([
                        TextInput::make('category')->maxLength(255),
                        Select::make('badges')
                            ->label('Badges')
                            ->multiple()
                            ->options(fn () => Badge::query()->pluck('name', 'id'))
                            ->searchable(),
                    ])
                    ->action(function (Profile $record, array $data): void {
                        $record->forceFill(['category' => $data['category'] ?? null])->save();

                        $sync = [];
                        foreach ($data['badges'] ?? [] as $badgeId) {
                            $sync[$badgeId] = ['awarded_at' => now()];
                        }
                        $record->badges()->sync($sync);

                        // Keep is_verified consistent with the verified badge.
                        $hasVerified = $record->badges()
                            ->where('key', 'verified')->exists();
                        if ($hasVerified !== (bool) $record->is_verified) {
                            $record->forceFill(['is_verified' => $hasVerified])->save();
                        }

                        Moderation::log('profile.edit_meta', $record, [
                            'category' => $data['category'] ?? null,
                            'badges' => $data['badges'] ?? [],
                        ]);

                        Notification::make()->title('Profile updated')->success()->send();
                    }),
            ]);
    }
}
