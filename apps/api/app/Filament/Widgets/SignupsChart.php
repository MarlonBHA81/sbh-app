<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;

class SignupsChart extends ChartWidget
{
    protected ?string $heading = 'Signups (last 30 days)';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $start = now()->subDays(29)->startOfDay();

        $counts = User::query()
            ->where('created_at', '>=', $start)
            ->get(['created_at'])
            ->groupBy(fn (User $user) => $user->created_at->format('Y-m-d'))
            ->map->count();

        $labels = [];
        $data = [];

        for ($i = 0; $i < 30; $i++) {
            $day = $start->copy()->addDays($i)->format('Y-m-d');
            $labels[] = $day;
            $data[] = $counts[$day] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Signups',
                    'data' => $data,
                    'fill' => 'start',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
