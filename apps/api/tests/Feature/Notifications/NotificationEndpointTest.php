<?php

use App\Models\Post;
use App\Models\Profile;
use App\Notifications\PostLiked;
use Illuminate\Support\Str;

/**
 * Seed a database notification for a user's active (personal) profile.
 */
function seedNotification($user, array $overrides = []): string
{
    $id = (string) Str::uuid();

    $user->notifications()->create(array_merge([
        'id' => $id,
        'type' => PostLiked::class,
        'data' => [
            'type' => 'post_liked',
            'actor' => ['ulid' => 'x', 'handle' => 'x', 'name' => 'X', 'avatar_url' => null],
            'target_profile_ulid' => $user->personalProfile->ulid,
        ],
        'read_at' => null,
    ], $overrides));

    return $id;
}

test('the notifications index is scoped to the active profile', function () {
    $user = userWithProfile();
    $business = Profile::factory()->business()->for($user)->create();

    seedNotification($user); // personal
    // A notification targeting the business profile should not appear for the personal view.
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => PostLiked::class,
        'data' => ['type' => 'post_liked', 'target_profile_ulid' => $business->ulid],
        'read_at' => null,
    ]);

    $this->actingAs($user)->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'post_liked');
});

test('the unread-count endpoint counts only the active profile unread notifications', function () {
    $user = userWithProfile();

    seedNotification($user);
    seedNotification($user);
    seedNotification($user, ['read_at' => now()]);

    $this->actingAs($user)->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('count', 2);
});

test('read-all marks every active-profile notification as read', function () {
    $user = userWithProfile();

    seedNotification($user);
    seedNotification($user);

    $this->actingAs($user)->postJson('/api/v1/notifications/read-all')->assertOk();

    $this->actingAs($user)->getJson('/api/v1/notifications/unread-count')
        ->assertJsonPath('count', 0);
});

test('a single notification can be marked read', function () {
    $user = userWithProfile();
    $id = seedNotification($user);

    $this->actingAs($user)->postJson("/api/v1/notifications/{$id}/read")
        ->assertOk()
        ->assertJsonPath('id', $id);

    expect($user->notifications()->find($id)->read_at)->not->toBeNull();
});

test('notifications land in the database when a real action occurs', function () {
    $author = userWithProfile();
    $liker = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($liker)->postJson("/api/v1/posts/{$post->ulid}/like")->assertOk();

    $this->actingAs($author)->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'post_liked')
        ->assertJsonPath('data.0.actor.handle', $liker->personalProfile->handle);
});

test('notifications require authentication', function () {
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
});
