<?php

use App\Models\Comment;
use App\Models\Follow;
use App\Models\Post;
use App\Notifications\CommentLiked;
use App\Notifications\CommentReplied;
use App\Notifications\FollowAccepted;
use App\Notifications\FollowRequested;
use App\Notifications\NewFollower;
use App\Notifications\PostCommented;
use App\Notifications\PostLiked;
use App\Notifications\PostQuoted;
use App\Notifications\PostReposted;
use Illuminate\Support\Facades\Notification;

test('following a public profile notifies the target with NewFollower', function () {
    Notification::fake();

    $follower = userWithProfile(['handle' => 'follower']);
    $target = userWithProfile(['handle' => 'target']);

    $this->actingAs($follower)->postJson('/api/v1/profiles/target/follow')->assertCreated();

    Notification::assertSentTo($target, NewFollower::class);
});

test('requesting to follow a private profile notifies with FollowRequested', function () {
    Notification::fake();

    $follower = userWithProfile(['handle' => 'follower']);
    $target = userWithProfile(['handle' => 'target', 'is_private' => true]);

    $this->actingAs($follower)->postJson('/api/v1/profiles/target/follow')->assertCreated();

    Notification::assertSentTo($target, FollowRequested::class);
    Notification::assertNotSentTo($target, NewFollower::class);
});

test('accepting a follow request notifies the requester with FollowAccepted', function () {
    Notification::fake();

    $follower = userWithProfile(['handle' => 'follower']);
    $target = userWithProfile(['handle' => 'target', 'is_private' => true]);

    $follow = Follow::create([
        'follower_profile_id' => $follower->personalProfile->id,
        'followed_profile_id' => $target->personalProfile->id,
        'state' => Follow::STATE_PENDING,
    ]);

    $this->actingAs($target)->postJson("/api/v1/me/follow-requests/{$follow->id}/accept")->assertOk();

    Notification::assertSentTo($follower, FollowAccepted::class);
});

test('liking a post notifies the author with PostLiked', function () {
    Notification::fake();

    $author = userWithProfile();
    $liker = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($liker)->postJson("/api/v1/posts/{$post->ulid}/like")->assertOk();

    Notification::assertSentTo($author, PostLiked::class);
});

test('a self-like does not notify', function () {
    Notification::fake();

    $author = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($author)->postJson("/api/v1/posts/{$post->ulid}/like")->assertOk();

    Notification::assertNothingSentTo($author);
});

test('commenting on a post notifies the author with PostCommented', function () {
    Notification::fake();

    $author = userWithProfile();
    $commenter = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($commenter)->postJson("/api/v1/posts/{$post->ulid}/comments", ['body' => 'hi'])->assertCreated();

    Notification::assertSentTo($author, PostCommented::class);
});

test('replying to a comment notifies the parent author with CommentReplied', function () {
    Notification::fake();

    $postAuthor = userWithProfile();
    $parentAuthor = userWithProfile();
    $replier = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $postAuthor->personalProfile->id]);
    $parent = Comment::factory()->create(['post_id' => $post->id, 'profile_id' => $parentAuthor->personalProfile->id]);

    $this->actingAs($replier)->postJson("/api/v1/posts/{$post->ulid}/comments", [
        'body' => 'a reply',
        'parent_comment_id' => $parent->ulid,
    ])->assertCreated();

    Notification::assertSentTo($parentAuthor, CommentReplied::class);
    Notification::assertSentTo($postAuthor, PostCommented::class);
});

test('liking a comment notifies its author with CommentLiked', function () {
    Notification::fake();

    $author = userWithProfile();
    $liker = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);
    $comment = Comment::factory()->create(['post_id' => $post->id, 'profile_id' => $author->personalProfile->id]);

    $this->actingAs($liker)->postJson("/api/v1/comments/{$comment->ulid}/like")->assertOk();

    Notification::assertSentTo($author, CommentLiked::class);
});

test('reposting a post notifies the original author with PostReposted', function () {
    Notification::fake();

    $author = userWithProfile();
    $reposter = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($reposter)->postJson('/api/v1/posts', [
        'type' => 'repost',
        'parent_post_id' => $post->ulid,
    ])->assertCreated();

    Notification::assertSentTo($author, PostReposted::class);
});

test('quoting a post notifies the original author with PostQuoted', function () {
    Notification::fake();

    $author = userWithProfile();
    $quoter = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($quoter)->postJson('/api/v1/posts', [
        'type' => 'quote',
        'body' => 'great take',
        'parent_post_id' => $post->ulid,
    ])->assertCreated();

    Notification::assertSentTo($author, PostQuoted::class);
});

test('a self-comment does not notify', function () {
    Notification::fake();

    $author = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($author)->postJson("/api/v1/posts/{$post->ulid}/comments", ['body' => 'my own note'])->assertCreated();

    Notification::assertNothingSentTo($author);
});
