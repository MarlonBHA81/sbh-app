<?php

namespace App\Filament\Widgets\Platform;

use Carbon\CarbonInterface;

/**
 * Shared helper for the platform analytics charts: builds a zero-filled 30-day
 * label/data pair from a per-day resolver keyed by Y-m-d.
 */
class PlatformSeries
{
    /**
     * @param  callable(string):(int|float)  $resolver
     * @return array{0: list<string>, 1: list<int|float>}
     */
    public static function zeroFilled(CarbonInterface $start, callable $resolver, int $days = 30): array
    {
        $labels = [];
        $data = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i)->format('Y-m-d');
            $labels[] = $day;
            $data[] = $resolver($day);
        }

        return [$labels, $data];
    }
}
