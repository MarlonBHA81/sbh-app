<?php

namespace Database\Factories;

use App\Models\Badge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Badge>
 */
class BadgeFactory extends Factory
{
    protected $model = Badge::class;

    public function definition(): array
    {
        return [
            'key' => Str::lower(Str::random(10)),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'icon' => null,
            'kind' => fake()->randomElement(['category', 'verification', 'rank']),
        ];
    }
}
