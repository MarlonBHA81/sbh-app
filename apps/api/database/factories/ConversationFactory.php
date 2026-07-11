<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'kind' => Conversation::KIND_DM,
            'created_by_profile_id' => Profile::factory(),
        ];
    }

    public function group(): static
    {
        return $this->state(fn () => [
            'kind' => Conversation::KIND_GROUP,
            'title' => fake()->words(3, true),
        ]);
    }
}
