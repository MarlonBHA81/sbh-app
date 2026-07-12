<?php

namespace App\Filament\Widgets\Platform;

use App\Models\AdEvent;
use Filament\Widgets\ChartWidget;

class AdSpendChart extends ChartWidget
{
    protected ?string $heading = 'Ad spend per day in Rand (last 30 days)';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    protected function getData(): array
    {
        $start = now()->subDays(29)->startOfDay();

        // Spend accrues one CPI per impression event; join each impression to
        // its campaign's cost-per-impression and sum per day.
        $spendCents = AdEvent::query()
            ->where('ad_events.kind', AdEvent::KIND_IMPRESSION)
            ->whereNotNull('ad_events.campaign_id')
            ->where('ad_events.created_at', '>=', $start)
            ->join('campaigns', 'ad_events.campaign_id', '=', 'campaigns.id')
            ->selectRaw('date(ad_events.created_at) as day, sum(campaigns.cpi_cents) as spend')
            ->groupBy('day')
            ->pluck('spend', 'day');

        [$labels, $data] = PlatformSeries::zeroFilled(
            $start,
            fn (string $day) => round(((int) ($spendCents[$day] ?? 0)) / 100, 2),
        );

        return [
            'datasets' => [
                ['label' => 'Spend (R)', 'data' => $data],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
