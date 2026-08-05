<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Platform\AdEventsChart;
use App\Filament\Widgets\Platform\AdSpendChart;
use App\Filament\Widgets\Platform\EngagementChart;
use App\Filament\Widgets\Platform\NewUsersChart;
use App\Filament\Widgets\Platform\PlatformStatsOverview;
use App\Filament\Widgets\Platform\PublishedPostsChart;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Super-admin-only platform analytics: a 30-day overview of growth, engagement
 * and ad performance. This is the super-admin superset of the regular admin
 * dashboard. Access is gated to super admins in both navigation and direct URL.
 */
class PlatformAnalytics extends Page
{
    protected string $view = 'filament.pages.platform-analytics';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Analytics';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Platform analytics';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            PlatformStatsOverview::class,
            NewUsersChart::class,
            PublishedPostsChart::class,
            EngagementChart::class,
            AdEventsChart::class,
            AdSpendChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }
}
