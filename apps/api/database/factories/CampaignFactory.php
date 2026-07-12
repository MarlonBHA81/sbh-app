<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        $profile = Profile::factory();

        return [
            'profile_id' => $profile,
            'post_id' => Post::factory(),
            'status' => Campaign::STATUS_ACTIVE,
            'budget_cents' => 10000,
            'spent_cents' => 0,
            'cpi_cents' => 2,
            'starts_at' => now(),
            'ends_at' => now()->addDays(7),
            'impressions_count' => 0,
            'clicks_count' => 0,
        ];
    }

    public function paused(): static
    {
        return $this->state(fn () => ['status' => Campaign::STATUS_PAUSED]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => Campaign::STATUS_COMPLETED]);
    }

    public function exhausted(): static
    {
        return $this->state(fn (array $attributes) => [
            'spent_cents' => $attributes['budget_cents'] ?? 10000,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
        ]);
    }
}
