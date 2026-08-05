<?php

namespace Database\Factories;

use App\Models\XpAction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<XpAction>
 */
class XpActionFactory extends Factory
{
    protected $model = XpAction::class;

    public function definition(): array
    {
        return [
            'key' => 'action_'.Str::lower(Str::random(10)),
            'label' => fake()->words(2, true),
            'points' => fake()->numberBetween(1, 20),
            'daily_cap' => null,
            'enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['enabled' => false]);
    }
}
