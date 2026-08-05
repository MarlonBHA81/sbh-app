<?php

namespace App\Filament\Resources\Profiles\Tables;

use App\Models\Badge;
use App\Models\BusinessCategory;
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
use Illuminate\Support\Str;

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
                IconColumn::make('is_facilitator')->boolean()->label('Facilitator')->sortable()->toggleable(),
                IconColumn::make('is_private')->boolean()->label('Private')->sortable(),
            ])
            ->filters([
                SelectFilter::make('kind')->options([
                    Profile::KIND_PERSONAL => 'Personal',
                    Profile::KIND_BUSINESS => 'Business',
                ]),
                TernaryFilter::make('is_verified')->label('Verified'),
                TernaryFilter::make('is_facilitator')->label('Facilitator'),
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

                Action::make('toggle_facilitator')
                    ->label(fn (Profile $record) => $record->is_facilitator ? 'Revoke facilitator' : 'Make facilitator')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->color(fn (Profile $record) => $record->is_facilitator ? 'gray' : 'success')
                    ->requiresConfirmation()
                    ->action(function (Profile $record): void {
                        $grant = ! $record->is_facilitator;
                        $record->forceFill(['is_facilitator' => $grant])->save();

                        Moderation::log($grant ? 'profile.facilitator_grant' : 'profile.facilitator_revoke', $record);

                        Notification::make()->title('Facilitator role updated')->success()->send();
                    }),

                Action::make('edit_meta')
                    ->label('Category & badges')
                    ->icon(Heroicon::OutlinedTag)
                    ->fillForm(fn (Profile $record) => [
                        'category' => $record->category,
                        'journey_stage' => $record->journey_stage,
                        'business_category_id' => $record->business_category_id,
                        'badges' => $record->badges()->pluck('badges.id')->all(),
                    ])
                    ->schema([
                        TextInput::make('category')->maxLength(255),
                        Select::make('journey_stage')
                            ->label('Business journey stage')
                            ->options(collect(Profile::JOURNEY_STAGES)
                                ->mapWithKeys(fn (string $s) => [$s => Str::headline($s)])
                                ->all())
                            ->nullable(),
                        Select::make('business_category_id')
                            ->label('Business category')
                            ->options(fn () => BusinessCategory::query()->orderBy('position')->pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->visible(fn (Profile $record) => $record->isBusiness()),
                        Select::make('badges')
                            ->label('Badges')
                            ->multiple()
                            ->options(fn () => Badge::query()->pluck('name', 'id'))
                            ->searchable(),
                    ])
                    ->action(function (Profile $record, array $data): void {
                        $update = [
                            'category' => $data['category'] ?? null,
                            'journey_stage' => $data['journey_stage'] ?? null,
                        ];

                        if ($record->isBusiness()) {
                            $update['business_category_id'] = $data['business_category_id'] ?? null;
                        }

                        $record->forceFill($update)->save();

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
