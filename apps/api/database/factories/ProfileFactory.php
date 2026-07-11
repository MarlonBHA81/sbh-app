<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => Profile::KIND_PERSONAL,
            'handle' => 'user_'.Str::lower(Str::random(12)),
            'name' => fake()->name(),
            'bio' => fake()->sentence(),
        ];
    }

    public function business(): static
    {
        return $this->state(fn () => [
            'kind' => Profile::KIND_BUSINESS,
            'name' => fake()->company(),
            'category' => fake()->randomElement(['restaurant', 'salon', 'retail', 'services']),
        ]);
    }

    public function private(): static
    {
        return $this->state(fn () => ['is_private' => true]);
    }
}
