<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\LatestSignups;
use App\Filament\Widgets\SignupsChart;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\TopProfiles;
use App\Filament\Widgets\XpLeaderboard;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\AccountWidget;

/**
 * The default admin dashboard. Its widget list is fixed here (rather than
 * inheriting every registered widget) so the super-admin-only platform
 * analytics widgets never leak onto the regular dashboard — they live solely
 * on the PlatformAnalytics page.
 */
class Dashboard extends BaseDashboard
{
    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            AccountWidget::class,
            StatsOverview::class,
            SignupsChart::class,
            LatestSignups::class,
            TopProfiles::class,
            XpLeaderboard::class,
        ];
    }
}
