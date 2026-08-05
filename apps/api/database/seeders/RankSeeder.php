<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\Rank;
use Illuminate\Database\Seeder;

class RankSeeder extends Seeder
{
    public function run(): void
    {
        $ranks = [
            ['newbie', 'Newbie', 0, '🌱'],
            ['rising', 'Rising', 100, '⭐'],
            ['contributor', 'Contributor', 500, '🔥'],
            ['expert', 'Expert', 2000, '💎'],
            ['legend', 'Legend', 10000, '👑'],
        ];

        foreach ($ranks as $position => [$key, $name, $minXp, $icon]) {
            $badge = Badge::query()->updateOrCreate(
                ['key' => 'rank_'.$key],
                [
                    'name' => $name,
                    'description' => 'Reached the '.$name.' rank.',
                    'icon' => $icon,
                    'kind' => 'rank',
                ],
            );

            Rank::query()->updateOrCreate(
                ['key' => $key],
                [
                    'name' => $name,
                    'min_xp' => $minXp,
                    'icon' => $icon,
                    'position' => $position,
                    'badge_id' => $badge->id,
                ],
            );
        }
    }
}
