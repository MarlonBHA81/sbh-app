<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\Programme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Programme>
 */
class ProgrammeFactory extends Factory
{
    protected $model = Programme::class;

    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory()->corporate(),
            'name' => fake()->words(3, true).' Programme',
            'description' => fake()->sentence(),
            'type' => Programme::TYPE_SUPPLIER_DEVELOPMENT,
            'status' => Programme::STATUS_DRAFT,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => Programme::STATUS_ACTIVE]);
    }
}
