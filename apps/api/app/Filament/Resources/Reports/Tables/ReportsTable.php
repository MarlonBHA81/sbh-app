<?php

namespace App\Filament\Resources\Reports\Tables;

use App\Filament\Resources\Reports\ReportActions;
use App\Models\Report;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['reporter', 'reportable', 'handler']))
            ->columns([
                TextColumn::make('category')->badge()->searchable()->sortable(),
                TextColumn::make('reportable_type')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state) => $state ? Str::lower(class_basename($state)) : '—')
                    ->badge(),
                TextColumn::make('reporter.handle')->label('Reporter')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        Report::STATUS_PENDING => 'warning',
                        Report::STATUS_REVIEWING => 'info',
                        Report::STATUS_RESOLVED => 'success',
                        Report::STATUS_DISMISSED => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')->dateTime()->label('Reported')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Report::STATUS_PENDING => 'Pending',
                        Report::STATUS_REVIEWING => 'Reviewing',
                        Report::STATUS_RESOLVED => 'Resolved',
                        Report::STATUS_DISMISSED => 'Dismissed',
                    ])
                    ->default(Report::STATUS_PENDING),
                SelectFilter::make('category')->options(array_combine(Report::CATEGORIES, Report::CATEGORIES)),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    ...ReportActions::all(),
                ]),
            ]);
    }
}
