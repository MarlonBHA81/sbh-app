<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'conversation_id' => Conversation::factory(),
            'profile_id' => Profile::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
