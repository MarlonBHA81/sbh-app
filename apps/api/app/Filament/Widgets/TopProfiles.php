<?php

namespace App\Filament\Widgets;

use App\Models\Profile;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopProfiles extends TableWidget
{
    protected static ?string $heading = 'Top profiles by followers';

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(Profile::query()->orderByDesc('followers_count')->limit(10))
            ->paginated(false)
            ->columns([
                TextColumn::make('handle')->label('Handle'),
                TextColumn::make('name')->label('Name'),
                TextColumn::make('followers_count')->label('Followers'),
            ]);
    }
}
