<?php

namespace App\Filament\Resources\SupplierEnrolments\RelationManagers;

use App\Models\ProgrammeMilestone;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Development milestones for a supplier enrolment, managed inline on the
 * enrolment edit page.
 */
class MilestonesRelationManager extends RelationManager
{
    protected static string $relationship = 'milestones';

    protected static ?string $title = 'Milestones';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(200),
            DateTimePicker::make('due_at')->seconds(false),
            Textarea::make('note')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => $state === ProgrammeMilestone::STATUS_COMPLETE ? 'success' : 'gray'),
                TextColumn::make('due_at')->dateTime()->placeholder('—')->sortable(),
                TextColumn::make('completed_at')->dateTime()->placeholder('—')->toggleable(),
            ])
            ->defaultSort('created_at')
            ->headerActions([CreateAction::make()])
            ->recordActions([
                Action::make('complete')
                    ->label('Complete')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (ProgrammeMilestone $record) => ! $record->isComplete())
                    ->action(function (ProgrammeMilestone $record): void {
                        $record->markComplete(auth()->user());

                        Notification::make()->title('Milestone completed')->success()->send();
                    }),
                Action::make('reopen')
                    ->label('Reopen')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->visible(fn (ProgrammeMilestone $record) => $record->isComplete())
                    ->action(function (ProgrammeMilestone $record): void {
                        $record->reopen(auth()->user());

                        Notification::make()->title('Milestone reopened')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
