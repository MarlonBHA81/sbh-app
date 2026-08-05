<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'profile_id' => Profile::factory(),
            'parent_comment_id' => null,
            'body' => fake()->sentence(),
            'depth' => 0,
        ];
    }

    public function replyTo(Comment $parent): static
    {
        return $this->state(fn () => [
            'post_id' => $parent->post_id,
            'parent_comment_id' => $parent->id,
            'depth' => $parent->depth + 1,
        ]);
    }
}
