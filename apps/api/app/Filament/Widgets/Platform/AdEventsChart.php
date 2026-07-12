<?php

namespace App\Filament\Widgets\Platform;

use App\Models\AdEvent;
use Filament\Widgets\ChartWidget;

class AdEventsChart extends ChartWidget
{
    protected ?string $heading = 'Ad impressions and clicks per day (last 30 days)';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    protected function getData(): array
    {
        $start = now()->subDays(29)->startOfDay();

        $rows = AdEvent::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('date(created_at) as day, kind, count(*) as total')
            ->groupBy('day', 'kind')
            ->get()
            ->groupBy('day');

        [$labels, $impressions] = PlatformSeries::zeroFilled($start, fn (string $day) => (int) (
            $rows->get($day, collect())->firstWhere('kind', AdEvent::KIND_IMPRESSION)?->total ?? 0
        ));
        [, $clicks] = PlatformSeries::zeroFilled($start, fn (string $day) => (int) (
            $rows->get($day, collect())->firstWhere('kind', AdEvent::KIND_CLICK)?->total ?? 0
        ));

        return [
            'datasets' => [
                ['label' => 'Impressions', 'data' => $impressions],
                ['label' => 'Clicks', 'data' => $clicks],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
