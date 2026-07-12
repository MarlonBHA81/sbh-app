<?php

namespace App\Filament\Widgets\Platform;

use App\Models\User;
use Filament\Widgets\ChartWidget;

class NewUsersChart extends ChartWidget
{
    protected ?string $heading = 'New users per day (last 30 days)';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    protected function getData(): array
    {
        $start = now()->subDays(29)->startOfDay();

        $counts = User::query()
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn (User $user) => $user->created_at->format('Y-m-d'))
            ->map->count();

        [$labels, $data] = PlatformSeries::zeroFilled($start, fn (string $day) => $counts[$day] ?? 0);

        return [
            'datasets' => [
                ['label' => 'New users', 'data' => $data, 'fill' => 'start'],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
