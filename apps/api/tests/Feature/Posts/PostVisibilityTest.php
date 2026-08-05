<?php

use App\Models\Post;

test('a followers-only post is hidden from non-followers and visible to followers', function () {
    $author = userWithProfile();
    $post = Post::factory()->followersOnly()->create(['profile_id' => $author->personalProfile->id]);

    $viewer = userWithProfile();

    $this->actingAs($viewer)
        ->getJson("/api/v1/posts/{$post->ulid}")
        ->assertNotFound();

    acceptedFollow($viewer->personalProfile, $author->personalProfile);

    $this->actingAs($viewer)
        ->getJson("/api/v1/posts/{$post->ulid}")
        ->assertOk()
        ->assertJsonPath('data.ulid', $post->ulid);

    $this->actingAs($author)
        ->getJson("/api/v1/posts/{$post->ulid}")
        ->assertOk();
});

test('posts of a private profile are hidden from non-followers', function () {
    $author = userWithProfile(['is_private' => true]);
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $viewer = userWithProfile();

    $this->actingAs($viewer)
        ->getJson("/api/v1/posts/{$post->ulid}")
        ->assertNotFound();

    acceptedFollow($viewer->personalProfile, $author->personalProfile);

    $this->actingAs($viewer)
        ->getJson("/api/v1/posts/{$post->ulid}")
        ->assertOk();
});

test('drafts are only visible to their author', function () {
    $author = userWithProfile();
    $draft = Post::factory()->draft()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($author)
        ->getJson("/api/v1/posts/{$draft->ulid}")
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');

    $viewer = userWithProfile();

    $this->actingAs($viewer)
        ->getJson("/api/v1/posts/{$draft->ulid}")
        ->assertNotFound();
});

test('a secret post hides its payload in show and list until revealed', function () {
    $author = userWithProfile(['handle' => 'keeper']);
    $post = Post::factory()->secret('the launch code is 1234')->create(['profile_id' => $author->personalProfile->id]);

    $viewer = userWithProfile();

    $this->actingAs($viewer)
        ->getJson("/api/v1/posts/{$post->ulid}")
        ->assertOk()
        ->assertJsonPath('data.payload.revealed', false)
        ->assertJsonMissingPath('data.payload.secret_text');

    $this->actingAs($viewer)
        ->getJson('/api/v1/profiles/keeper/posts')
        ->assertOk()
        ->assertJsonPath('data.0.payload.revealed', false)
        ->assertJsonMissingPath('data.0.payload.secret_text');

    $this->actingAs($viewer)
        ->postJson("/api/v1/posts/{$post->ulid}/reveal")
        ->assertOk()
        ->assertJsonPath('data.payload.secret_text', 'the launch code is 1234')
        ->assertJsonPath('data.views_count', 1);

    expect($post->refresh()->views_count)->toBe(1);
});

test('reveal returns the magnifier payload and counts a view', function () {
    $author = userWithProfile();
    $post = Post::factory()->create([
        'profile_id' => $author->personalProfile->id,
        'type' => 'magnifier',
        'body' => null,
        'payload' => ['text' => 'zoom in'],
    ]);

    $viewer = userWithProfile();

    // Magnifier text stays in the payload (blurred client-side).
    $this->actingAs($viewer)
        ->getJson("/api/v1/posts/{$post->ulid}")
        ->assertOk()
        ->assertJsonPath('data.payload.text', 'zoom in');

    $this->actingAs($viewer)
        ->postJson("/api/v1/posts/{$post->ulid}/reveal")
        ->assertOk()
        ->assertJsonPath('data.payload.text', 'zoom in')
        ->assertJsonPath('data.views_count', 1);
});

test('reveal respects post visibility', function () {
    $author = userWithProfile();
    $post = Post::factory()->followersOnly()->secret()->create(['profile_id' => $author->personalProfile->id]);

    $viewer = userWithProfile();

    $this->actingAs($viewer)
        ->postJson("/api/v1/posts/{$post->ulid}/reveal")
        ->assertNotFound();
});
