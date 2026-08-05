<?php

namespace App\Filament\Widgets;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $since = now()->subDays(7);

        return [
            Stat::make('Total users', (string) User::query()->count()),
            Stat::make('New users (7d)', (string) User::query()->where('created_at', '>=', $since)->count()),
            Stat::make('Total posts', (string) Post::query()->count()),
            Stat::make('Posts (7d)', (string) Post::query()->where('created_at', '>=', $since)->count()),
            Stat::make('Pending reports', (string) Report::query()->where('status', Report::STATUS_PENDING)->count())
                ->color('danger'),
            Stat::make('Active campaigns', (string) Campaign::query()->where('status', Campaign::STATUS_ACTIVE)->count()),
        ];
    }
}
