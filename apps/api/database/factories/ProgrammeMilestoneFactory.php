<?php

namespace Database\Factories;

use App\Models\ProgrammeMilestone;
use App\Models\SupplierEnrolment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProgrammeMilestone>
 */
class ProgrammeMilestoneFactory extends Factory
{
    protected $model = ProgrammeMilestone::class;

    public function definition(): array
    {
        return [
            'supplier_enrolment_id' => SupplierEnrolment::factory(),
            'title' => fake()->sentence(3),
            'status' => ProgrammeMilestone::STATUS_PENDING,
        ];
    }

    public function complete(): static
    {
        return $this->state(fn () => [
            'status' => ProgrammeMilestone::STATUS_COMPLETE,
            'completed_at' => now(),
        ]);
    }
}
