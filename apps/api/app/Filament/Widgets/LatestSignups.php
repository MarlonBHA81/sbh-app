<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestSignups extends TableWidget
{
    protected static ?string $heading = 'Latest signups';

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(User::query()->latest()->limit(10))
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('Name'),
                TextColumn::make('email')->label('Email'),
                TextColumn::make('created_at')->since()->label('Joined'),
            ]);
    }
}
