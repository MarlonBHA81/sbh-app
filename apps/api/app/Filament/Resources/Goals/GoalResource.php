<?php

namespace App\Filament\Resources\Goals;

use App\Filament\Resources\Goals\Pages\ListGoals;
use App\Models\Goal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Member goals (V3 · PROGRESS) — read-only admin visibility. Goals are set and
 * completed by members through the app; admins only observe them here to gauge
 * how the community is using the dashboard. No admin create/edit.
 */
class GoalResource extends Resource
{
    protected static ?string $model = Goal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 5;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('profile.name')->label('Member')->searchable()->limit(30),
                TextColumn::make('title')->searchable()->limit(40),
                TextColumn::make('target')->toggleable()->placeholder('—')->limit(30),
                TextColumn::make('due_on')->date()->sortable()->placeholder('—'),
                IconColumn::make('is_done')->boolean()->label('Done')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_done')->label('Completed'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGoals::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
