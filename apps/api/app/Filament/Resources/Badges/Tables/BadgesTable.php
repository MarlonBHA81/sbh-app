<?php

namespace App\Filament\Resources\Badges\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BadgesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('kind')->badge()->sortable(),
                TextColumn::make('icon')->toggleable(),
                TextColumn::make('profiles_count')->counts('profiles')->label('Holders'),
            ])
            ->filters([
                SelectFilter::make('kind')->options([
                    'category' => 'Category',
                    'verification' => 'Verification',
                    'rank' => 'Rank',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
