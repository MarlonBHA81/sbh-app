<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Support\Moderation;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable()->copyable(),
                TextColumn::make('profiles_count')->counts('profiles')->label('Profiles')->sortable(),
                IconColumn::make('is_admin')->boolean()->label('Admin')->sortable(),
                TextColumn::make('banned_at')->dateTime()->label('Banned')->placeholder('—')->sortable(),
                TextColumn::make('created_at')->dateTime()->label('Joined')->sortable()->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('banned')
                    ->label('Banned')
                    ->nullable()
                    ->attribute('banned_at'),
                TernaryFilter::make('is_admin')->label('Admins'),
            ])
            ->recordActions([
                Action::make('ban')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (User $record) => ! $record->isBanned())
                    ->schema([
                        Textarea::make('reason')->label('Reason')->required()->maxLength(500),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->forceFill([
                            'banned_at' => now(),
                            'ban_reason' => $data['reason'],
                        ])->save();

                        $record->tokens()->delete();

                        Moderation::log('user.ban', $record, ['reason' => $data['reason']]);

                        Notification::make()->title('User banned')->success()->send();
                    }),

                Action::make('unban')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => $record->isBanned())
                    ->action(function (User $record): void {
                        $record->forceFill(['banned_at' => null, 'ban_reason' => null])->save();

                        Moderation::log('user.unban', $record);

                        Notification::make()->title('User unbanned')->success()->send();
                    }),

                Action::make('toggle_admin')
                    ->label(fn (User $record) => $record->is_admin ? 'Revoke admin' : 'Make admin')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $record->forceFill(['is_admin' => ! $record->is_admin])->save();

                        Moderation::log($record->is_admin ? 'user.promote' : 'user.demote', $record);

                        Notification::make()->title('Admin status updated')->success()->send();
                    }),
            ]);
    }
}
