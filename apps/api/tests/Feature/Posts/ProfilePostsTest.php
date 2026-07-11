<?php

use App\Models\Post;
use App\Models\Profile;

test('profile posts lists published posts respecting visibility', function () {
    $author = userWithProfile(['handle' => 'writer']);
    $profile = $author->personalProfile;

    Post::factory()->create(['profile_id' => $profile->id, 'body' => 'public post']);
    Post::factory()->followersOnly()->create(['profile_id' => $profile->id, 'body' => 'inner circle']);
    Post::factory()->draft()->create(['profile_id' => $profile->id]);

    $viewer = userWithProfile();

    // Non-follower: public published posts only.
    $this->actingAs($viewer)
        ->getJson('/api/v1/profiles/writer/posts')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'public post');

    acceptedFollow($viewer->personalProfile, $profile);

    // Follower: followers-only posts included; drafts never listed.
    $this->actingAs($viewer)
        ->getJson('/api/v1/profiles/writer/posts')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    // Self: also sees followers-only posts, still published only.
    $this->actingAs($author)
        ->getJson('/api/v1/profiles/writer/posts')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('posts of a private profile are not listed for non-followers', function () {
    $author = userWithProfile(['handle' => 'hermit', 'is_private' => true]);
    Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $viewer = userWithProfile();

    $this->actingAs($viewer)
        ->getJson('/api/v1/profiles/hermit/posts')
        ->assertForbidden();

    acceptedFollow($viewer->personalProfile, $author->personalProfile);

    $this->actingAs($viewer)
        ->getJson('/api/v1/profiles/hermit/posts')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('me/posts returns own posts filtered by status', function () {
    $user = userWithProfile();
    $profile = $user->personalProfile;

    Post::factory()->count(2)->draft()->create(['profile_id' => $profile->id]);
    Post::factory()->scheduled()->create(['profile_id' => $profile->id]);
    Post::factory()->create(['profile_id' => $profile->id]);

    $this->actingAs($user)
        ->getJson('/api/v1/me/posts')
        ->assertOk()
        ->assertJsonCount(4, 'data');

    $this->actingAs($user)
        ->getJson('/api/v1/me/posts?status=draft')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.status', 'draft');

    $this->actingAs($user)
        ->getJson('/api/v1/me/posts?status=bogus')
        ->assertUnprocessable();
});

test('me/posts is scoped to the active profile', function () {
    $user = userWithProfile();
    $business = Profile::factory()->business()->for($user)->create();

    Post::factory()->create(['profile_id' => $user->personalProfile->id, 'body' => 'personal']);
    Post::factory()->create(['profile_id' => $business->id, 'body' => 'business']);

    $this->actingAs($user)
        ->withHeader('X-Profile-Id', $business->ulid)
        ->getJson('/api/v1/me/posts')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.body', 'business');
});

test('post lists are cursor paginated', function () {
    $author = userWithProfile(['handle' => 'prolific']);
    Post::factory()->count(25)->create(['profile_id' => $author->personalProfile->id]);

    $viewer = userWithProfile();

    $first = $this->actingAs($viewer)
        ->getJson('/api/v1/profiles/prolific/posts')
        ->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['path', 'per_page', 'next_cursor', 'prev_cursor'],
        ]);

    $nextCursor = $first->json('meta.next_cursor');
    expect($nextCursor)->not->toBeNull();

    $this->actingAs($viewer)
        ->getJson('/api/v1/profiles/prolific/posts?cursor='.$nextCursor)
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.next_cursor', null);
});
