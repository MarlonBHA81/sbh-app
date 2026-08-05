<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Models\Post;
use App\Support\Moderation;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ulid')->label('ULID')->searchable()->limit(12)->copyable(),
                TextColumn::make('type')->badge()->sortable(),
                TextColumn::make('profile.handle')->label('Author')->searchable()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('likes_count')->label('Likes')->sortable(),
                TextColumn::make('comments_count')->label('Comments')->sortable(),
                IconColumn::make('sensitive')->boolean()->sortable(),
                TextColumn::make('created_at')->dateTime()->label('Created')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(fn () => Post::query()
                    ->distinct()->orderBy('type')->pluck('type', 'type')->all()),
                SelectFilter::make('status')->options([
                    Post::STATUS_DRAFT => 'Draft',
                    Post::STATUS_SCHEDULED => 'Scheduled',
                    Post::STATUS_PUBLISHED => 'Published',
                ]),
                TernaryFilter::make('sensitive'),
            ])
            ->recordActions([
                Action::make('view_payload')
                    ->label('Payload')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading('Post content')
                    ->modalSubmitAction(false)
                    ->fillForm(fn (Post $record) => [
                        'body' => $record->body,
                        'payload' => json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    ])
                    ->schema([
                        Textarea::make('body')->disabled()->rows(3),
                        Textarea::make('payload')->disabled()->rows(12),
                    ]),

                Action::make('toggle_sensitive')
                    ->label(fn (Post $record) => $record->sensitive ? 'Unmark sensitive' : 'Mark sensitive')
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (Post $record): void {
                        $record->forceFill(['sensitive' => ! $record->sensitive])->save();

                        Moderation::log('post.toggle_sensitive', $record, ['sensitive' => $record->sensitive]);

                        Notification::make()->title('Sensitivity updated')->success()->send();
                    }),

                DeleteAction::make()
                    ->after(fn (Post $record) => Moderation::log('post.delete', $record)),
            ]);
    }
}
