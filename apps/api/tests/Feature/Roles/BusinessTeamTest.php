<?php

use App\Models\Post;
use App\Models\Profile;

function businessTeam(): array
{
    $owner = userWithProfile();
    $business = Profile::factory()->business()->for($owner)->create();
    $teammate = userWithProfile();

    return [$owner, $business, $teammate];
}

test('an owner can add a member by handle and it appears on the team', function () {
    [$owner, $business, $teammate] = businessTeam();

    $this->actingAs($owner)
        ->postJson("/api/v1/me/profiles/{$business->ulid}/members", [
            'handle' => $teammate->profiles()->first()->handle,
            'role' => 'poster',
        ])
        ->assertCreated()
        ->assertJsonPath('data.0.role', 'owner')
        ->assertJsonPath('data.1.role', 'poster');
});

test('a member can act as the business profile but a stranger cannot', function () {
    [$owner, $business, $teammate] = businessTeam();
    $stranger = userWithProfile();

    $this->actingAs($owner)->postJson("/api/v1/me/profiles/{$business->ulid}/members", [
        'handle' => $teammate->profiles()->first()->handle,
        'role' => 'poster',
    ])->assertCreated();

    // Member switches to the business profile.
    $this->actingAs($teammate)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('active_profile.ulid', $business->ulid)
        ->assertJsonPath('active_profile.my_role', 'poster');

    // A non-member is refused.
    $this->actingAs($stranger)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->getJson('/api/v1/me')
        ->assertForbidden();
});

test('a post under a business profile records the acting member as author', function () {
    [$owner, $business, $teammate] = businessTeam();

    $this->actingAs($owner)->postJson("/api/v1/me/profiles/{$business->ulid}/members", [
        'handle' => $teammate->profiles()->first()->handle,
        'role' => 'poster',
    ])->assertCreated();

    $this->actingAs($teammate)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->postJson('/api/v1/posts', ['type' => 'text', 'body' => 'From the team'])
        ->assertCreated();

    $post = Post::query()->latest('id')->first();

    expect($post->profile_id)->toBe($business->id)
        ->and($post->author_user_id)->toBe($teammate->id);
});

test('a poster cannot manage members but a manager can', function () {
    [$owner, $business, $teammate] = businessTeam();
    $handle = $teammate->profiles()->first()->handle;

    $this->actingAs($owner)->postJson("/api/v1/me/profiles/{$business->ulid}/members", [
        'handle' => $handle, 'role' => 'poster',
    ])->assertCreated();

    $newcomer = userWithProfile();

    // A poster cannot add members.
    $this->actingAs($teammate)
        ->postJson("/api/v1/me/profiles/{$business->ulid}/members", [
            'handle' => $newcomer->profiles()->first()->handle, 'role' => 'poster',
        ])
        ->assertForbidden();

    // Promote to manager, then they can.
    $this->actingAs($owner)
        ->patchJson("/api/v1/me/profiles/{$business->ulid}/members/{$handle}", ['role' => 'manager'])
        ->assertOk();

    $this->actingAs($teammate)
        ->postJson("/api/v1/me/profiles/{$business->ulid}/members", [
            'handle' => $newcomer->profiles()->first()->handle, 'role' => 'poster',
        ])
        ->assertCreated();
});

test('the owner cannot be removed or reassigned', function () {
    [$owner, $business] = businessTeam();
    $ownerHandle = $owner->profiles()->first()->handle;

    $this->actingAs($owner)
        ->deleteJson("/api/v1/me/profiles/{$business->ulid}/members/{$ownerHandle}")
        ->assertForbidden();

    $this->actingAs($owner)
        ->patchJson("/api/v1/me/profiles/{$business->ulid}/members/{$ownerHandle}", ['role' => 'manager'])
        ->assertForbidden();
});

test('personal profiles have no team', function () {
    $user = userWithProfile();
    $personal = $user->profiles()->first();
    $other = userWithProfile();

    $this->actingAs($user)
        ->postJson("/api/v1/me/profiles/{$personal->ulid}/members", [
            'handle' => $other->profiles()->first()->handle, 'role' => 'poster',
        ])
        ->assertForbidden();
});

test('a member sees the shared business profile in their profile list', function () {
    [$owner, $business, $teammate] = businessTeam();

    $this->actingAs($owner)->postJson("/api/v1/me/profiles/{$business->ulid}/members", [
        'handle' => $teammate->profiles()->first()->handle, 'role' => 'manager',
    ])->assertCreated();

    $ulids = collect(
        $this->actingAs($teammate)->getJson('/api/v1/me')->assertOk()->json('profiles')
    )->pluck('ulid');

    expect($ulids)->toContain($business->ulid);
});
