<?php

use App\Models\Post;

test('the author can edit body and sensitive of a published post', function () {
    $user = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $user->personalProfile->id]);

    $this->actingAs($user)
        ->patchJson("/api/v1/posts/{$post->ulid}", ['body' => 'edited', 'sensitive' => true])
        ->assertOk()
        ->assertJsonPath('data.body', 'edited')
        ->assertJsonPath('data.sensitive', true);
});

test('published posts cannot change status, visibility or schedule', function (array $data, string $errorKey) {
    $user = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $user->personalProfile->id]);

    $this->actingAs($user)
        ->patchJson("/api/v1/posts/{$post->ulid}", $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errorKey);
})->with([
    'status' => [['status' => 'draft'], 'status'],
    'visibility' => [['visibility' => 'followers'], 'visibility'],
    'scheduled_at' => [['scheduled_at' => '2030-01-01 10:00:00'], 'scheduled_at'],
]);

test('payload edits are validated against the type registry', function () {
    $user = userWithProfile();
    $post = Post::factory()->create([
        'profile_id' => $user->personalProfile->id,
        'type' => 'link',
        'body' => null,
        'payload' => ['url' => 'https://example.com'],
    ]);

    $this->actingAs($user)
        ->patchJson("/api/v1/posts/{$post->ulid}", ['payload' => ['url' => 'not-a-url']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payload.url');

    $this->actingAs($user)
        ->patchJson("/api/v1/posts/{$post->ulid}", ['payload' => ['url' => 'https://example.org', 'title' => 'New']])
        ->assertOk()
        ->assertJsonPath('data.payload.url', 'https://example.org')
        ->assertJsonPath('data.payload.title', 'New');
});

test('drafts are fully editable', function () {
    $user = userWithProfile();
    $draft = Post::factory()->draft()->create(['profile_id' => $user->personalProfile->id]);

    $this->actingAs($user)
        ->patchJson("/api/v1/posts/{$draft->ulid}", [
            'body' => 'new body',
            'visibility' => 'followers',
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay()->toDateTimeString(),
        ])
        ->assertOk()
        ->assertJsonPath('data.body', 'new body')
        ->assertJsonPath('data.visibility', 'followers')
        ->assertJsonPath('data.status', 'scheduled');
});

test('non-authors cannot edit or delete a post', function () {
    $author = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $other = userWithProfile();

    $this->actingAs($other)
        ->patchJson("/api/v1/posts/{$post->ulid}", ['body' => 'hijack'])
        ->assertForbidden();

    $this->actingAs($other)
        ->deleteJson("/api/v1/posts/{$post->ulid}")
        ->assertForbidden();

    expect($post->refresh()->trashed())->toBeFalse();
});

test('deleting a post soft deletes it and decrements posts_count', function () {
    $user = userWithProfile();

    $ulid = $this->actingAs($user)
        ->postJson('/api/v1/posts', ['type' => 'text', 'body' => 'bye'])
        ->assertCreated()
        ->json('data.ulid');

    expect($user->personalProfile->refresh()->posts_count)->toBe(1);

    $this->actingAs($user)
        ->deleteJson("/api/v1/posts/{$ulid}")
        ->assertNoContent();

    expect($user->personalProfile->refresh()->posts_count)->toBe(0)
        ->and(Post::withTrashed()->where('ulid', $ulid)->first()->trashed())->toBeTrue();

    $this->actingAs($user)
        ->getJson("/api/v1/posts/{$ulid}")
        ->assertNotFound();
});

test('deleting a repost decrements the parent reposts_count', function () {
    $user = userWithProfile();
    $parent = Post::factory()->create();

    $ulid = $this->actingAs($user)
        ->postJson('/api/v1/posts', ['type' => 'repost', 'parent_post_id' => $parent->ulid])
        ->assertCreated()
        ->json('data.ulid');

    expect($parent->refresh()->reposts_count)->toBe(1);

    $this->actingAs($user)
        ->deleteJson("/api/v1/posts/{$ulid}")
        ->assertNoContent();

    expect($parent->refresh()->reposts_count)->toBe(0);
});
