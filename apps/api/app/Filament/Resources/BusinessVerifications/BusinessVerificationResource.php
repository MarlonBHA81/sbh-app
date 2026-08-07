<?php

namespace App\Filament\Resources\BusinessVerifications;

use App\Filament\Resources\BusinessVerifications\Pages\ListBusinessVerifications;
use App\Filament\Resources\BusinessVerifications\Pages\ViewBusinessVerification;
use App\Filament\Resources\BusinessVerifications\Schemas\BusinessVerificationInfolist;
use App\Filament\Resources\BusinessVerifications\Tables\BusinessVerificationsTable;
use App\Models\BusinessVerification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BusinessVerificationResource extends Resource
{
    protected static ?string $model = BusinessVerification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Verifications';

    public static function table(Table $table): Table
    {
        return BusinessVerificationsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BusinessVerificationInfolist::configure($schema);
    }

    /** Pending submissions awaiting a reviewer, shown in the nav. */
    public static function getNavigationBadge(): ?string
    {
        $count = BusinessVerification::query()->where('status', BusinessVerification::STATUS_PENDING)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBusinessVerifications::route('/'),
            'view' => ViewBusinessVerification::route('/{record}'),
        ];
    }
}
