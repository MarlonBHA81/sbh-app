<?php

use App\Events\ReactionUpdated;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reaction;
use Illuminate\Support\Facades\Event;

test('liking a post is idempotent and bumps the counter once', function () {
    $author = userWithProfile();
    $liker = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($liker)->postJson("/api/v1/posts/{$post->ulid}/like")
        ->assertOk()
        ->assertJsonPath('data.likes_count', 1)
        ->assertJsonPath('data.liked', true);

    // Second like is a no-op.
    $this->actingAs($liker)->postJson("/api/v1/posts/{$post->ulid}/like")->assertOk();

    expect($post->fresh()->likes_count)->toBe(1)
        ->and(Reaction::where('bucket', 'like')->count())->toBe(1);
});

test('unliking a post removes the like and decrements the counter', function () {
    $author = userWithProfile();
    $liker = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($liker)->postJson("/api/v1/posts/{$post->ulid}/like");
    $this->actingAs($liker)->deleteJson("/api/v1/posts/{$post->ulid}/like")
        ->assertOk()
        ->assertJsonPath('data.likes_count', 0)
        ->assertJsonPath('data.liked', false);

    expect($post->fresh()->likes_count)->toBe(0)
        ->and(Reaction::count())->toBe(0);
});

test('unliking when not liked keeps the counter at zero', function () {
    $author = userWithProfile();
    $liker = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($liker)->deleteJson("/api/v1/posts/{$post->ulid}/like")
        ->assertOk()
        ->assertJsonPath('data.likes_count', 0);

    expect($post->fresh()->likes_count)->toBe(0);
});

test('votes switch from up to down to clear with correct counters', function () {
    $author = userWithProfile();
    $voter = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    // up
    $this->actingAs($voter)->postJson("/api/v1/posts/{$post->ulid}/vote", ['value' => 1])
        ->assertOk()
        ->assertJsonPath('data.upvotes_count', 1)
        ->assertJsonPath('data.downvotes_count', 0)
        ->assertJsonPath('data.my_vote', 1);

    // switch to down
    $this->actingAs($voter)->postJson("/api/v1/posts/{$post->ulid}/vote", ['value' => -1])
        ->assertOk()
        ->assertJsonPath('data.upvotes_count', 0)
        ->assertJsonPath('data.downvotes_count', 1)
        ->assertJsonPath('data.my_vote', -1);

    // clear
    $this->actingAs($voter)->postJson("/api/v1/posts/{$post->ulid}/vote", ['value' => 0])
        ->assertOk()
        ->assertJsonPath('data.upvotes_count', 0)
        ->assertJsonPath('data.downvotes_count', 0)
        ->assertJsonPath('data.my_vote', 0);

    expect(Reaction::where('bucket', 'vote')->count())->toBe(0);
});

test('a like and a vote coexist independently on the same post', function () {
    $author = userWithProfile();
    $actor = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($actor)->postJson("/api/v1/posts/{$post->ulid}/like");
    $this->actingAs($actor)->postJson("/api/v1/posts/{$post->ulid}/vote", ['value' => 1]);

    expect(Reaction::where('bucket', 'like')->count())->toBe(1)
        ->and(Reaction::where('bucket', 'vote')->count())->toBe(1);

    $this->actingAs($actor)->getJson("/api/v1/posts/{$post->ulid}")
        ->assertJsonPath('data.liked', true)
        ->assertJsonPath('data.my_vote', 1);
});

test('an invalid vote value is rejected', function () {
    $author = userWithProfile();
    $voter = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($voter)->postJson("/api/v1/posts/{$post->ulid}/vote", ['value' => 5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('value');
});

test('comment likes and votes maintain their own counters', function () {
    $author = userWithProfile();
    $actor = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);
    $comment = Comment::factory()->create(['post_id' => $post->id, 'profile_id' => $author->personalProfile->id]);

    $this->actingAs($actor)->postJson("/api/v1/comments/{$comment->ulid}/like")
        ->assertOk()
        ->assertJsonPath('data.likes_count', 1)
        ->assertJsonPath('data.liked', true);

    $this->actingAs($actor)->postJson("/api/v1/comments/{$comment->ulid}/vote", ['value' => -1])
        ->assertOk()
        ->assertJsonPath('data.downvotes_count', 1)
        ->assertJsonPath('data.my_vote', -1);

    expect($comment->fresh()->likes_count)->toBe(1)
        ->and($comment->fresh()->downvotes_count)->toBe(1);
});

test('a ReactionUpdated event is broadcast on like and vote', function () {
    Event::fake([ReactionUpdated::class]);

    $author = userWithProfile();
    $actor = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($actor)->postJson("/api/v1/posts/{$post->ulid}/like");
    $this->actingAs($actor)->postJson("/api/v1/posts/{$post->ulid}/vote", ['value' => 1]);

    Event::assertDispatched(ReactionUpdated::class, fn (ReactionUpdated $e) => $e->post->is($post));
    Event::assertDispatchedTimes(ReactionUpdated::class, 2);
});

test('reacting requires authentication', function () {
    $post = Post::factory()->create();

    $this->postJson("/api/v1/posts/{$post->ulid}/like")->assertUnauthorized();
});
