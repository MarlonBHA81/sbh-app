<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'type' => 'text',
            'body' => fake()->sentence(),
            'visibility' => Post::VISIBILITY_PUBLIC,
            'status' => Post::STATUS_PUBLISHED,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => Post::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }

    public function scheduled(?\DateTimeInterface $at = null): static
    {
        return $this->state(fn () => [
            'status' => Post::STATUS_SCHEDULED,
            'scheduled_at' => $at ?? now()->addHour(),
            'published_at' => null,
        ]);
    }

    public function followersOnly(): static
    {
        return $this->state(fn () => ['visibility' => Post::VISIBILITY_FOLLOWERS]);
    }

    public function secret(string $secretText = 'the secret'): static
    {
        return $this->state(fn () => [
            'type' => Post::TYPE_SECRET,
            'body' => null,
            'payload' => ['secret_text' => $secretText],
        ]);
    }

    public function repostOf(Post $parent): static
    {
        return $this->state(fn () => [
            'type' => Post::TYPE_REPOST,
            'body' => null,
            'parent_post_id' => $parent->id,
        ]);
    }
}
