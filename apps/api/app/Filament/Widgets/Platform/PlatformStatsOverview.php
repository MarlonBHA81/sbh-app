<?php

namespace App\Filament\Widgets\Platform;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\PostStatsDaily;
use App\Models\Report;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    protected function getStats(): array
    {
        $totalViews = (int) PostStatsDaily::query()->sum('views');
        $totalSpendCents = (int) Campaign::query()->sum('spent_cents');

        return [
            Stat::make('Total users', (string) User::query()->count()),
            Stat::make('Total posts', (string) Post::query()->count()),
            Stat::make('Total views', (string) $totalViews),
            Stat::make('Pending reports', (string) Report::query()->where('status', Report::STATUS_PENDING)->count())
                ->color('danger'),
            Stat::make('Active campaigns', (string) Campaign::query()->where('status', Campaign::STATUS_ACTIVE)->count()),
            Stat::make('Total ad spend', 'R'.number_format($totalSpendCents / 100, 2)),
        ];
    }
}
