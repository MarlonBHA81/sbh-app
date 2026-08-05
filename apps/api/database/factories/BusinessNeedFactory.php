<?php

namespace Database\Factories;

use App\Models\BusinessCategory;
use App\Models\BusinessNeed;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessNeed>
 */
class BusinessNeedFactory extends Factory
{
    protected $model = BusinessNeed::class;

    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory()->business(),
            'kind' => fake()->randomElement([BusinessNeed::KIND_OFFERING, BusinessNeed::KIND_SEEKING]),
            'business_category_id' => BusinessCategory::factory(),
            'description' => fake()->sentence(),
            'active' => true,
        ];
    }

    public function offering(): static
    {
        return $this->state(fn () => ['kind' => BusinessNeed::KIND_OFFERING]);
    }

    public function seeking(): static
    {
        return $this->state(fn () => ['kind' => BusinessNeed::KIND_SEEKING]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
