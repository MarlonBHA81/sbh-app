<?php

namespace Database\Factories;

use App\Models\AdSlot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AdSlot>
 */
class AdSlotFactory extends Factory
{
    protected $model = AdSlot::class;

    public function definition(): array
    {
        return [
            'key' => 'slot-'.Str::lower(Str::random(8)),
            'placement' => AdSlot::PLACEMENT_RIGHT_RAIL,
            'name' => fake()->words(2, true),
            'sponsor_name' => fake()->company(),
            'sponsor_url' => fake()->url(),
            'image_path' => null,
            'body' => fake()->sentence(4),
            'active' => true,
            'weight' => 1,
        ];
    }

    public function placement(string $placement): static
    {
        return $this->state(fn () => ['placement' => $placement]);
    }

    public function weight(int $weight): static
    {
        return $this->state(fn () => ['weight' => $weight]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
