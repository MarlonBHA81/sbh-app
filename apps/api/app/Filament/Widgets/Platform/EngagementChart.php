<?php

namespace App\Filament\Widgets\Platform;

use App\Models\PostStatsDaily;
use Filament\Widgets\ChartWidget;

class EngagementChart extends ChartWidget
{
    protected ?string $heading = 'Views and likes per day (last 30 days)';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    protected function getData(): array
    {
        $start = now()->subDays(29)->startOfDay();

        $rows = PostStatsDaily::query()
            ->where('date', '>=', $start->toDateString())
            ->selectRaw('date, sum(views) as views, sum(likes) as likes')
            ->groupBy('date')
            ->get()
            ->keyBy(fn (PostStatsDaily $row) => $row->date->format('Y-m-d'));

        [$labels, $views] = PlatformSeries::zeroFilled($start, fn (string $day) => (int) ($rows[$day]->views ?? 0));
        [, $likes] = PlatformSeries::zeroFilled($start, fn (string $day) => (int) ($rows[$day]->likes ?? 0));

        return [
            'datasets' => [
                ['label' => 'Views', 'data' => $views, 'fill' => 'start'],
                ['label' => 'Likes', 'data' => $likes, 'fill' => 'start'],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
