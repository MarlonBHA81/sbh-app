<?php

namespace Database\Factories;

use App\Models\Cohort;
use App\Models\Programme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cohort>
 */
class CohortFactory extends Factory
{
    protected $model = Cohort::class;

    public function definition(): array
    {
        return [
            'programme_id' => Programme::factory(),
            'name' => fake()->words(2, true).' Cohort',
            'status' => Cohort::STATUS_ACTIVE,
        ];
    }
}
