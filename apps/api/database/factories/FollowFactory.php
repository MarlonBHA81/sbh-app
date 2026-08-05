<?php

namespace Database\Factories;

use App\Models\Follow;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Follow>
 */
class FollowFactory extends Factory
{
    protected $model = Follow::class;

    public function definition(): array
    {
        return [
            'follower_profile_id' => Profile::factory(),
            'followed_profile_id' => Profile::factory(),
            'state' => Follow::STATE_ACCEPTED,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['state' => Follow::STATE_PENDING]);
    }
}
