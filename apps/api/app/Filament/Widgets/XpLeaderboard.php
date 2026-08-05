<?php

namespace App\Filament\Widgets;

use App\Models\Profile;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class XpLeaderboard extends TableWidget
{
    protected static ?string $heading = 'XP leaderboard (all-time)';

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(Profile::query()->with('rank')->orderByDesc('xp_total')->limit(10))
            ->paginated(false)
            ->columns([
                TextColumn::make('handle')->label('Handle'),
                TextColumn::make('name')->label('Name'),
                TextColumn::make('rank.name')->label('Rank')->badge()->placeholder('—'),
                TextColumn::make('xp_total')->label('XP')->numeric(),
            ]);
    }
}
