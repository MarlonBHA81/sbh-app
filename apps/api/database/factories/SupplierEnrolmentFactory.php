<?php

namespace Database\Factories;

use App\Models\Cohort;
use App\Models\Profile;
use App\Models\SupplierEnrolment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierEnrolment>
 */
class SupplierEnrolmentFactory extends Factory
{
    protected $model = SupplierEnrolment::class;

    public function definition(): array
    {
        return [
            'cohort_id' => Cohort::factory(),
            'profile_id' => Profile::factory()->business()->state(['is_verified' => true]),
            'status' => SupplierEnrolment::STATUS_INVITED,
        ];
    }

    public function applied(): static
    {
        return $this->state(fn () => ['status' => SupplierEnrolment::STATUS_APPLIED]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => SupplierEnrolment::STATUS_ACCEPTED,
            'enrolled_at' => now(),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => SupplierEnrolment::STATUS_ACTIVE,
            'enrolled_at' => now(),
        ]);
    }
}
