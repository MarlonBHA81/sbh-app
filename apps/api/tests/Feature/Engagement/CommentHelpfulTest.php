<?php

use App\Models\Comment;
use App\Models\Post;
use App\Models\XpAction;
use App\Services\Gamification\GamificationService as G;

beforeEach(function () {
    XpAction::query()->create([
        'key' => G::HELPFUL_RECEIVED,
        'label' => 'A comment was marked helpful',
        'points' => 20,
        'daily_cap' => null,
    ]);
});

function helpfulSetup(): array
{
    $author = userWithProfile();
    $helper = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);
    $comment = Comment::factory()->create([
        'post_id' => $post->id,
        'profile_id' => $helper->personalProfile->id,
    ]);

    return [$author, $helper, $post, $comment];
}

test('the post author can mark a comment helpful, rewarding the answerer', function () {
    [$author, $helper, , $comment] = helpfulSetup();

    $this->actingAs($author)
        ->postJson("/api/v1/comments/{$comment->ulid}/helpful")
        ->assertOk()
        ->assertJsonPath('data.is_helpful', true);

    $helperProfile = $helper->personalProfile->fresh();
    expect($helperProfile->xp_total)->toBe(20);
    expect($helperProfile->helpful_count)->toBe(1);
});

test('only the post author can mark a comment helpful', function () {
    [, $helper, , $comment] = helpfulSetup();
    $stranger = userWithProfile();

    $this->actingAs($stranger)
        ->postJson("/api/v1/comments/{$comment->ulid}/helpful")
        ->assertForbidden();

    // The commenter can't self-award either.
    $this->actingAs($helper)
        ->postJson("/api/v1/comments/{$comment->ulid}/helpful")
        ->assertForbidden();
});

test('the post author cannot mark their own comment helpful', function () {
    $author = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);
    $own = Comment::factory()->create([
        'post_id' => $post->id,
        'profile_id' => $author->personalProfile->id,
    ]);

    $this->actingAs($author)
        ->postJson("/api/v1/comments/{$own->ulid}/helpful")
        ->assertStatus(422);
});

test('marking helpful twice does not double the reward', function () {
    [$author, $helper, , $comment] = helpfulSetup();

    $this->actingAs($author)->postJson("/api/v1/comments/{$comment->ulid}/helpful")->assertOk();
    $this->actingAs($author)->postJson("/api/v1/comments/{$comment->ulid}/helpful")->assertOk();

    $helperProfile = $helper->personalProfile->fresh();
    expect($helperProfile->xp_total)->toBe(20);
    expect($helperProfile->helpful_count)->toBe(1);
});

test('unmarking helpful clears the flag and the count', function () {
    [$author, $helper, , $comment] = helpfulSetup();

    $this->actingAs($author)->postJson("/api/v1/comments/{$comment->ulid}/helpful")->assertOk();
    $this->actingAs($author)
        ->deleteJson("/api/v1/comments/{$comment->ulid}/helpful")
        ->assertOk()
        ->assertJsonPath('data.is_helpful', false);

    $helperProfile = $helper->personalProfile->fresh();
    expect($helperProfile->helpful_count)->toBe(0);
    // XP already earned stays (ledger isn't clawed back).
    expect($helperProfile->xp_total)->toBe(20);
});
