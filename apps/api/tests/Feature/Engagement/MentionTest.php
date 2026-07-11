<?php

use App\Models\Comment;
use App\Models\Mention;
use App\Models\Post;
use App\Notifications\Mentioned;
use Illuminate\Support\Facades\Notification;

test('publishing a post records mention rows and notifies mentioned profiles', function () {
    Notification::fake();

    $author = userWithProfile(['handle' => 'author_one']);
    $alice = userWithProfile(['handle' => 'alice']);
    $bob = userWithProfile(['handle' => 'bob']);

    $this->actingAs($author)->postJson('/api/v1/posts', [
        'type' => 'text',
        'body' => 'hey @alice and @bob welcome',
    ])->assertCreated();

    $post = Post::where('profile_id', $author->personalProfile->id)->first();

    expect(Mention::where('mentionable_type', $post->getMorphClass())->count())->toBe(2);

    Notification::assertSentTo($alice, Mentioned::class);
    Notification::assertSentTo($bob, Mentioned::class);
});

test('mentions are de-duplicated and self-mentions are skipped', function () {
    Notification::fake();

    $author = userWithProfile(['handle' => 'writer']);
    $alice = userWithProfile(['handle' => 'alice']);

    $this->actingAs($author)->postJson('/api/v1/posts', [
        'type' => 'text',
        'body' => '@alice @alice @writer talking to myself and @alice',
    ])->assertCreated();

    $post = Post::where('profile_id', $author->personalProfile->id)->first();

    // Only one mention row for alice; none for the self-mention.
    expect(Mention::where('mentionable_type', $post->getMorphClass())->count())->toBe(1)
        ->and(Mention::where('mentioned_profile_id', $alice->personalProfile->id)->count())->toBe(1)
        ->and(Mention::where('mentioned_profile_id', $author->personalProfile->id)->count())->toBe(0);

    Notification::assertSentToTimes($alice, Mentioned::class, 1);
});

test('mentions of nonexistent handles are ignored', function () {
    Notification::fake();

    $author = userWithProfile(['handle' => 'writer']);

    $this->actingAs($author)->postJson('/api/v1/posts', [
        'type' => 'text',
        'body' => 'hello @ghost_user nobody home',
    ])->assertCreated();

    expect(Mention::count())->toBe(0);
    Notification::assertNothingSent();
});

test('comment bodies are parsed for mentions', function () {
    Notification::fake();

    $author = userWithProfile();
    $commenter = userWithProfile(['handle' => 'commenter']);
    $alice = userWithProfile(['handle' => 'alice']);
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($commenter)->postJson("/api/v1/posts/{$post->ulid}/comments", [
        'body' => 'cc @alice look at this',
    ])->assertCreated();

    $comment = Comment::where('profile_id', $commenter->personalProfile->id)->first();

    expect(Mention::where('mentionable_type', $comment->getMorphClass())->count())->toBe(1);
    Notification::assertSentTo($alice, Mentioned::class);
});
