<?php

namespace App\Filament\Widgets\Platform;

use App\Models\Post;
use Filament\Widgets\ChartWidget;

class PublishedPostsChart extends ChartWidget
{
    protected ?string $heading = 'Published posts per day (last 30 days)';

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->is_super_admin;
    }

    protected function getData(): array
    {
        $start = now()->subDays(29)->startOfDay();

        $counts = Post::query()
            ->where('status', Post::STATUS_PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '>=', $start)
            ->get(['published_at'])
            ->groupBy(fn (Post $post) => $post->published_at->format('Y-m-d'))
            ->map->count();

        [$labels, $data] = PlatformSeries::zeroFilled($start, fn (string $day) => $counts[$day] ?? 0);

        return [
            'datasets' => [
                ['label' => 'Published posts', 'data' => $data, 'fill' => 'start'],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
