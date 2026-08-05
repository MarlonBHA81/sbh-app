<?php

namespace App\Filament\Resources\AdSlots;

use App\Filament\Resources\AdSlots\Pages\CreateAdSlot;
use App\Filament\Resources\AdSlots\Pages\EditAdSlot;
use App\Filament\Resources\AdSlots\Pages\ListAdSlots;
use App\Filament\Resources\AdSlots\Schemas\AdSlotForm;
use App\Filament\Resources\AdSlots\Tables\AdSlotsTable;
use App\Models\AdSlot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AdSlotResource extends Resource
{
    protected static ?string $model = AdSlot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Ads';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AdSlotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdSlotsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdSlots::route('/'),
            'create' => CreateAdSlot::route('/create'),
            'edit' => EditAdSlot::route('/{record}/edit'),
        ];
    }
}
