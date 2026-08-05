<?php

use App\Models\Follow;

test('following a public profile is accepted immediately and updates counters', function () {
    $follower = userWithProfile(['handle' => 'follower_one']);
    $target = userWithProfile(['handle' => 'target_public']);

    $this->actingAs($follower)
        ->postJson('/api/v1/profiles/target_public/follow')
        ->assertCreated()
        ->assertJsonPath('state', 'accepted')
        ->assertJsonPath('relationship', 'following');

    expect($follower->personalProfile->fresh()->following_count)->toBe(1)
        ->and($target->personalProfile->fresh()->followers_count)->toBe(1);
});

test('following a private profile creates a pending request without counters', function () {
    $follower = userWithProfile(['handle' => 'follower_two']);
    $target = userWithProfile(['handle' => 'target_private', 'is_private' => true]);

    $this->actingAs($follower)
        ->postJson('/api/v1/profiles/target_private/follow')
        ->assertCreated()
        ->assertJsonPath('state', 'pending')
        ->assertJsonPath('relationship', 'pending');

    expect($follower->personalProfile->fresh()->following_count)->toBe(0)
        ->and($target->personalProfile->fresh()->followers_count)->toBe(0);
});

test('a profile cannot follow itself', function () {
    $user = userWithProfile(['handle' => 'narcissist']);

    $this->actingAs($user)
        ->postJson('/api/v1/profiles/narcissist/follow')
        ->assertUnprocessable();
});

test('unfollowing decrements counters', function () {
    $follower = userWithProfile(['handle' => 'follower_three']);
    $target = userWithProfile(['handle' => 'target_unfollow']);

    $this->actingAs($follower)->postJson('/api/v1/profiles/target_unfollow/follow');

    $this->actingAs($follower)
        ->deleteJson('/api/v1/profiles/target_unfollow/follow')
        ->assertNoContent();

    expect($follower->personalProfile->fresh()->following_count)->toBe(0)
        ->and($target->personalProfile->fresh()->followers_count)->toBe(0)
        ->and(Follow::count())->toBe(0);
});

test('cancelling a pending request removes it', function () {
    $follower = userWithProfile(['handle' => 'follower_four']);
    userWithProfile(['handle' => 'target_cancel', 'is_private' => true]);

    $this->actingAs($follower)->postJson('/api/v1/profiles/target_cancel/follow');

    $this->actingAs($follower)
        ->deleteJson('/api/v1/profiles/target_cancel/follow')
        ->assertNoContent();

    expect(Follow::count())->toBe(0);
});

test('pending follow requests are listed for the active profile', function () {
    $follower = userWithProfile(['handle' => 'requester']);
    $target = userWithProfile(['handle' => 'requested', 'is_private' => true]);

    $this->actingAs($follower)->postJson('/api/v1/profiles/requested/follow');

    $this->actingAs($target->fresh())
        ->getJson('/api/v1/me/follow-requests')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.state', 'pending')
        ->assertJsonPath('data.0.follower.handle', 'requester');
});

test('accepting a follow request updates state and counters', function () {
    $follower = userWithProfile(['handle' => 'accept_me']);
    $target = userWithProfile(['handle' => 'acceptor', 'is_private' => true]);

    $this->actingAs($follower)->postJson('/api/v1/profiles/acceptor/follow');

    $follow = Follow::first();

    $this->actingAs($target)
        ->postJson("/api/v1/me/follow-requests/{$follow->id}/accept")
        ->assertOk()
        ->assertJsonPath('data.state', 'accepted');

    expect($follower->personalProfile->fresh()->following_count)->toBe(1)
        ->and($target->personalProfile->fresh()->followers_count)->toBe(1);
});

test('declining a follow request deletes it without touching counters', function () {
    $follower = userWithProfile(['handle' => 'decline_me']);
    $target = userWithProfile(['handle' => 'decliner', 'is_private' => true]);

    $this->actingAs($follower)->postJson('/api/v1/profiles/decliner/follow');

    $follow = Follow::first();

    $this->actingAs($target)
        ->postJson("/api/v1/me/follow-requests/{$follow->id}/decline")
        ->assertNoContent();

    expect(Follow::count())->toBe(0)
        ->and($target->personalProfile->fresh()->followers_count)->toBe(0);
});

test('only the target profile owner can accept a follow request', function () {
    $follower = userWithProfile(['handle' => 'sneaky_follower']);
    userWithProfile(['handle' => 'sneaky_target', 'is_private' => true]);
    $stranger = userWithProfile(['handle' => 'stranger']);

    $this->actingAs($follower)->postJson('/api/v1/profiles/sneaky_target/follow');

    $follow = Follow::first();

    $this->actingAs($stranger)
        ->postJson("/api/v1/me/follow-requests/{$follow->id}/accept")
        ->assertForbidden();
});

test('followers of a public profile are listed', function () {
    $follower = userWithProfile(['handle' => 'listed_follower']);
    userWithProfile(['handle' => 'popular']);

    $this->actingAs($follower)->postJson('/api/v1/profiles/popular/follow');

    $this->getJson('/api/v1/profiles/popular/followers')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.handle', 'listed_follower');
});

test('followers of a private profile are hidden from non-followers', function () {
    userWithProfile(['handle' => 'hidden_target', 'is_private' => true]);
    $viewer = userWithProfile();

    $this->actingAs($viewer)
        ->getJson('/api/v1/profiles/hidden_target/followers')
        ->assertForbidden();

    $this->getJson('/api/v1/profiles/hidden_target/following')->assertForbidden();
});

test('followers of a private profile are visible to accepted followers and the owner', function () {
    $follower = userWithProfile(['handle' => 'inner_circle']);
    $target = userWithProfile(['handle' => 'private_star', 'is_private' => true]);

    $this->actingAs($follower)->postJson('/api/v1/profiles/private_star/follow');

    $follow = Follow::first();

    $this->actingAs($target)->postJson("/api/v1/me/follow-requests/{$follow->id}/accept");

    $this->actingAs($follower)
        ->getJson('/api/v1/profiles/private_star/followers')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($target)
        ->getJson('/api/v1/profiles/private_star/followers')
        ->assertOk();
});

test('an accepted follower sees the full private profile', function () {
    $follower = userWithProfile(['handle' => 'trusted']);
    $target = userWithProfile(['handle' => 'private_full', 'is_private' => true, 'bio' => 'Inner secrets']);

    $this->actingAs($follower)->postJson('/api/v1/profiles/private_full/follow');

    $this->actingAs($target)->postJson('/api/v1/me/follow-requests/'.Follow::first()->id.'/accept');

    $this->actingAs($follower)
        ->getJson('/api/v1/profiles/private_full')
        ->assertOk()
        ->assertJsonPath('data.limited', false)
        ->assertJsonPath('data.bio', 'Inner secrets')
        ->assertJsonPath('data.relationship', 'following');
});

test('following twice does not duplicate the follow or counters', function () {
    $follower = userWithProfile(['handle' => 'double_tap']);
    $target = userWithProfile(['handle' => 'double_target']);

    $this->actingAs($follower)->postJson('/api/v1/profiles/double_target/follow');
    $this->actingAs($follower)->postJson('/api/v1/profiles/double_target/follow')->assertCreated();

    expect(Follow::count())->toBe(1)
        ->and($target->personalProfile->fresh()->followers_count)->toBe(1);
});
