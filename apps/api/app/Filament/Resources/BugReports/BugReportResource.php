<?php

namespace App\Filament\Resources\BugReports;

use App\Filament\Resources\BugReports\Pages\ListBugReports;
use App\Filament\Resources\BugReports\Pages\ViewBugReport;
use App\Filament\Resources\BugReports\Schemas\BugReportInfolist;
use App\Filament\Resources\BugReports\Tables\BugReportsTable;
use App\Models\BugReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BugReportResource extends Resource
{
    protected static ?string $model = BugReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBugAnt;

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return BugReportsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return BugReportInfolist::configure($schema);
    }

    /** Open bug reports awaiting triage, shown in the nav. */
    public static function getNavigationBadge(): ?string
    {
        $count = BugReport::query()->where('status', BugReport::STATUS_OPEN)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBugReports::route('/'),
            'view' => ViewBugReport::route('/{record}'),
        ];
    }
}
