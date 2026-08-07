<?php

namespace Database\Factories;

use App\Models\Disbursement;
use App\Models\SupplierEnrolment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Disbursement>
 */
class DisbursementFactory extends Factory
{
    protected $model = Disbursement::class;

    public function definition(): array
    {
        return [
            'supplier_enrolment_id' => SupplierEnrolment::factory(),
            'amount_cents' => fake()->numberBetween(50_000, 5_000_000),
            'currency' => 'ZAR',
            'kind' => Disbursement::KIND_GRANT,
        ];
    }

    /** A line that has actually been paid out. */
    public function paid(): static
    {
        return $this->state(fn () => ['disbursed_at' => now()]);
    }
}
