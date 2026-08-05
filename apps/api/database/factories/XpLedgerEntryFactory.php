<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\XpLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<XpLedgerEntry>
 */
class XpLedgerEntryFactory extends Factory
{
    protected $model = XpLedgerEntry::class;

    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'action_key' => 'post_published',
            'points' => 10,
        ];
    }
}
