<?php

namespace App\Filament\Resources\XpActions;

use App\Filament\Resources\XpActions\Pages\CreateXpAction;
use App\Filament\Resources\XpActions\Pages\EditXpAction;
use App\Filament\Resources\XpActions\Pages\ListXpActions;
use App\Filament\Resources\XpActions\Schemas\XpActionForm;
use App\Filament\Resources\XpActions\Tables\XpActionsTable;
use App\Models\XpAction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class XpActionResource extends Resource
{
    protected static ?string $model = XpAction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'Gamification';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return XpActionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return XpActionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListXpActions::route('/'),
            'create' => CreateXpAction::route('/create'),
            'edit' => EditXpAction::route('/{record}/edit'),
        ];
    }
}
