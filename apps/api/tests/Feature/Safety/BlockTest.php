<?php

use App\Models\Block;
use App\Models\Follow;
use App\Models\Post;

test('blocking a profile creates a block record', function () {
    $actor = userWithProfile(['handle' => 'blocker']);
    userWithProfile(['handle' => 'target']);

    $this->actingAs($actor)
        ->postJson('/api/v1/profiles/target/block')
        ->assertCreated()
        ->assertJsonPath('status', 'blocked');

    expect(Block::count())->toBe(1);
});

test('unblocking removes the block record', function () {
    $actor = userWithProfile(['handle' => 'unblocker']);
    userWithProfile(['handle' => 'freed']);

    $this->actingAs($actor)->postJson('/api/v1/profiles/freed/block');

    $this->actingAs($actor)
        ->deleteJson('/api/v1/profiles/freed/block')
        ->assertNoContent();

    expect(Block::count())->toBe(0);
});

test('a profile cannot block itself', function () {
    $actor = userWithProfile(['handle' => 'lonely']);

    $this->actingAs($actor)
        ->postJson('/api/v1/profiles/lonely/block')
        ->assertUnprocessable();
});

test('blocking tears down follows in both directions and fixes counters', function () {
    $actor = userWithProfile(['handle' => 'a_side']);
    $target = userWithProfile(['handle' => 'b_side']);

    acceptedFollow($actor->personalProfile, $target->personalProfile);
    acceptedFollow($target->personalProfile, $actor->personalProfile);

    // Reflect the two accepted follows in the counters.
    $actor->personalProfile->update(['following_count' => 1, 'followers_count' => 1]);
    $target->personalProfile->update(['following_count' => 1, 'followers_count' => 1]);

    $this->actingAs($actor)->postJson('/api/v1/profiles/b_side/block')->assertCreated();

    expect(Follow::count())->toBe(0)
        ->and($actor->personalProfile->fresh()->following_count)->toBe(0)
        ->and($actor->personalProfile->fresh()->followers_count)->toBe(0)
        ->and($target->personalProfile->fresh()->following_count)->toBe(0)
        ->and($target->personalProfile->fresh()->followers_count)->toBe(0);
});

test('blocking deletes a pending follow request', function () {
    $actor = userWithProfile(['handle' => 'req_blocker']);
    $target = userWithProfile(['handle' => 'req_target', 'is_private' => true]);

    Follow::factory()->pending()->create([
        'follower_profile_id' => $target->personalProfile->id,
        'followed_profile_id' => $actor->personalProfile->id,
    ]);

    $this->actingAs($actor)->postJson('/api/v1/profiles/req_target/block')->assertCreated();

    expect(Follow::count())->toBe(0);
});

test('a blocked pair cannot see each others posts directly (both directions)', function () {
    $actor = userWithProfile(['handle' => 'viewer_a']);
    $target = userWithProfile(['handle' => 'viewer_b']);

    $actorPost = Post::factory()->create(['profile_id' => $actor->personalProfile->id]);
    $targetPost = Post::factory()->create(['profile_id' => $target->personalProfile->id]);

    $this->actingAs($actor)->postJson('/api/v1/profiles/viewer_b/block');

    // Blocker cannot see the blocked user's post.
    $this->actingAs($actor)->getJson("/api/v1/posts/{$targetPost->ulid}")->assertNotFound();

    // Blocked user cannot see the blocker's post either.
    $this->actingAs($target)->getJson("/api/v1/posts/{$actorPost->ulid}")->assertNotFound();
});

test('blocked authors are excluded from the following feed', function () {
    $viewer = userWithProfile(['handle' => 'feed_viewer']);
    $blocked = userWithProfile(['handle' => 'feed_blocked']);

    acceptedFollow($viewer->personalProfile, $blocked->personalProfile);
    $post = Post::factory()->create(['profile_id' => $blocked->personalProfile->id]);

    // Visible before the block.
    $before = collect($this->actingAs($viewer)->getJson('/api/v1/feeds/following')->json('data'))->pluck('ulid');
    expect($before)->toContain($post->ulid);

    $this->actingAs($viewer)->postJson('/api/v1/profiles/feed_blocked/block');

    $after = collect($this->actingAs($viewer)->getJson('/api/v1/feeds/following')->json('data'))->pluck('ulid');
    expect($after)->not->toContain($post->ulid);
});

test('blocked authors are excluded from the for-you feed', function () {
    $viewer = userWithProfile(['handle' => 'fy_viewer']);
    $blocked = userWithProfile(['handle' => 'fy_blocked']);

    acceptedFollow($viewer->personalProfile, $blocked->personalProfile);
    $post = Post::factory()->create(['profile_id' => $blocked->personalProfile->id, 'score' => 100]);

    $this->actingAs($viewer)->postJson('/api/v1/profiles/fy_blocked/block');

    $ulids = collect($this->actingAs($viewer)->getJson('/api/v1/feeds/for-you')->json('data'))->pluck('ulid');
    expect($ulids)->not->toContain($post->ulid);
});

test('a blocked profile returns 403 when listing the other profiles posts', function () {
    $actor = userWithProfile(['handle' => 'list_a']);
    userWithProfile(['handle' => 'list_b']);

    $this->actingAs($actor)->postJson('/api/v1/profiles/list_b/block');

    $this->actingAs($actor)->getJson('/api/v1/profiles/list_b/posts')->assertForbidden();
});

test('blocked profiles are excluded from typeahead search (both directions)', function () {
    $actor = userWithProfile(['handle' => 'search_a', 'name' => 'Search Alpha']);
    $target = userWithProfile(['handle' => 'search_b', 'name' => 'Search Beta']);

    $this->actingAs($actor)->postJson('/api/v1/profiles/search_b/block');

    $actorResults = collect($this->actingAs($actor)->getJson('/api/v1/search/profiles?q=search')->json('data'))->pluck('handle');
    expect($actorResults)->not->toContain('search_b');

    $targetResults = collect($this->actingAs($target)->getJson('/api/v1/search/profiles?q=search')->json('data'))->pluck('handle');
    expect($targetResults)->not->toContain('search_a');
});

test('blocked authors comments are hidden from the blocker', function () {
    $author = userWithProfile(['handle' => 'post_owner']);
    $commenter = userWithProfile(['handle' => 'mean_commenter']);

    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($commenter)->postJson("/api/v1/posts/{$post->ulid}/comments", ['body' => 'nasty']);

    // Author blocks the commenter, then reads the comments.
    $this->actingAs($author)->postJson('/api/v1/profiles/mean_commenter/block');

    $this->actingAs($author)
        ->getJson("/api/v1/posts/{$post->ulid}/comments")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('interactions on a blocked users content are denied', function () {
    $actor = userWithProfile(['handle' => 'int_a']);
    $target = userWithProfile(['handle' => 'int_b']);

    $targetPost = Post::factory()->create(['profile_id' => $target->personalProfile->id]);

    $this->actingAs($actor)->postJson('/api/v1/profiles/int_b/block');

    $this->actingAs($actor)->postJson("/api/v1/posts/{$targetPost->ulid}/like")->assertNotFound();
    $this->actingAs($actor)->postJson("/api/v1/posts/{$targetPost->ulid}/comments", ['body' => 'hi'])->assertForbidden();
    $this->actingAs($actor)->postJson('/api/v1/profiles/int_b/follow')->assertNotFound();
});

test('the blocker sees a blocked relationship and limited profile', function () {
    $actor = userWithProfile(['handle' => 'shape_a']);
    userWithProfile(['handle' => 'shape_b', 'bio' => 'secret bio']);

    $this->actingAs($actor)->postJson('/api/v1/profiles/shape_b/block');

    $this->actingAs($actor)
        ->getJson('/api/v1/profiles/shape_b')
        ->assertOk()
        ->assertJsonPath('data.relationship', 'blocked')
        ->assertJsonPath('data.limited', true)
        ->assertJsonMissingPath('data.bio');
});

test('the blocked user is not told they are blocked', function () {
    $actor = userWithProfile(['handle' => 'hide_a']);
    $target = userWithProfile(['handle' => 'hide_b', 'bio' => 'private bio']);

    $this->actingAs($actor)->postJson('/api/v1/profiles/hide_b/block');

    // The blocked party sees a private/unavailable shape with no 'blocked' hint.
    $this->actingAs($target)
        ->getJson('/api/v1/profiles/hide_a')
        ->assertOk()
        ->assertJsonPath('data.relationship', 'none')
        ->assertJsonPath('data.limited', true)
        ->assertJsonPath('data.is_private', true);
});

test('me/blocks lists profiles the viewer blocked', function () {
    $actor = userWithProfile(['handle' => 'owner_blocks']);
    userWithProfile(['handle' => 'blocked_one']);

    $this->actingAs($actor)->postJson('/api/v1/profiles/blocked_one/block');

    $this->actingAs($actor)
        ->getJson('/api/v1/me/blocks')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.handle', 'blocked_one');
});
