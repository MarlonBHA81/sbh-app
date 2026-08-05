<?php

namespace App\Filament\Resources\AdSlots\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdSlotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')->label('Image')->disk('public'),
                TextColumn::make('key')->searchable()->sortable(),
                TextColumn::make('placement')->badge()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('sponsor_name')->label('Sponsor')->searchable(),
                TextColumn::make('weight')->sortable(),
                IconColumn::make('active')->boolean()->sortable(),
                TextColumn::make('events_count')->counts('events')->label('Events'),
            ])
            ->defaultSort('placement')
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
