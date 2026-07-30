<?php

namespace App\Filament\Resources\BugReports\Tables;

use App\Filament\Resources\BugReports\BugReportActions;
use App\Models\BugReport;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BugReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'profile', 'handler']))
            ->columns([
                TextColumn::make('summary')->wrap()->searchable()->limit(60),
                TextColumn::make('user.email')->label('Reporter')->searchable()->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        BugReport::STATUS_OPEN => 'warning',
                        BugReport::STATUS_TRIAGED => 'info',
                        BugReport::STATUS_RESOLVED => 'success',
                        BugReport::STATUS_DISMISSED => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('app_version')->label('Version')->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->dateTime()->label('Reported')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        BugReport::STATUS_OPEN => 'Open',
                        BugReport::STATUS_TRIAGED => 'Triaged',
                        BugReport::STATUS_RESOLVED => 'Resolved',
                        BugReport::STATUS_DISMISSED => 'Dismissed',
                    ])
                    ->default(BugReport::STATUS_OPEN),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    ...BugReportActions::all(),
                ]),
            ]);
    }
}
