<?php

namespace Database\Factories;

use App\Models\Rank;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Rank>
 */
class RankFactory extends Factory
{
    protected $model = Rank::class;

    public function definition(): array
    {
        return [
            'key' => 'rank_'.Str::lower(Str::random(8)),
            'name' => fake()->word(),
            'min_xp' => 0,
            'icon' => null,
            'position' => 0,
            'badge_id' => null,
        ];
    }
}
