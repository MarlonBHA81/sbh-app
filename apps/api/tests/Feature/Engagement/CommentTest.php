<?php

use App\Events\CommentAdded;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Support\Facades\Event;

test('a comment can be created and bumps the post comment count', function () {
    $author = userWithProfile();
    $commenter = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($commenter)->postJson("/api/v1/posts/{$post->ulid}/comments", ['body' => 'Nice post'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Nice post')
        ->assertJsonPath('data.depth', 0)
        ->assertJsonPath('data.parent_comment_ulid', null);

    expect($post->fresh()->comments_count)->toBe(1);
});

test('replies increase depth and the parent reply count', function () {
    $author = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);
    $parent = Comment::factory()->create(['post_id' => $post->id, 'profile_id' => $author->personalProfile->id]);

    $this->actingAs($author)->postJson("/api/v1/posts/{$post->ulid}/comments", [
        'body' => 'a reply',
        'parent_comment_id' => $parent->ulid,
    ])
        ->assertCreated()
        ->assertJsonPath('data.depth', 1)
        ->assertJsonPath('data.parent_comment_ulid', $parent->ulid);

    expect($parent->fresh()->replies_count)->toBe(1)
        ->and($post->fresh()->comments_count)->toBe(1);
});

test('comments_count aggregates top-level comments and replies', function () {
    $author = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($author)->postJson("/api/v1/posts/{$post->ulid}/comments", ['body' => 'top']);
    $top = Comment::latest('id')->first();
    $this->actingAs($author)->postJson("/api/v1/posts/{$post->ulid}/comments", ['body' => 'reply', 'parent_comment_id' => $top->ulid]);

    expect($post->fresh()->comments_count)->toBe(2);
});

test('replies cannot be nested beyond the depth cap', function () {
    $author = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    // depth 0 -> 1 -> 2 -> 3 are allowed; a reply to depth 3 (=> depth 4) is rejected.
    $d0 = Comment::factory()->create(['post_id' => $post->id, 'profile_id' => $author->personalProfile->id, 'depth' => 0]);
    $d1 = Comment::factory()->create(['post_id' => $post->id, 'profile_id' => $author->personalProfile->id, 'parent_comment_id' => $d0->id, 'depth' => 1]);
    $d2 = Comment::factory()->create(['post_id' => $post->id, 'profile_id' => $author->personalProfile->id, 'parent_comment_id' => $d1->id, 'depth' => 2]);
    $d3 = Comment::factory()->create(['post_id' => $post->id, 'profile_id' => $author->personalProfile->id, 'parent_comment_id' => $d2->id, 'depth' => 3]);

    // Reply to depth-2 (=> depth 3) is fine.
    $this->actingAs($author)->postJson("/api/v1/posts/{$post->ulid}/comments", ['body' => 'ok', 'parent_comment_id' => $d2->ulid])
        ->assertCreated()
        ->assertJsonPath('data.depth', 3);

    // Reply to depth-3 (=> depth 4) is rejected.
    $this->actingAs($author)->postJson("/api/v1/posts/{$post->ulid}/comments", ['body' => 'too deep', 'parent_comment_id' => $d3->ulid])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_comment_id');
});

test('the comment body is validated for length', function () {
    $author = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($author)->postJson("/api/v1/posts/{$post->ulid}/comments", ['body' => str_repeat('x', 1001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('body');
});

test('a comment cannot be added to a post the viewer cannot see', function () {
    $author = userWithProfile(['is_private' => true]);
    $outsider = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($outsider)->postJson("/api/v1/posts/{$post->ulid}/comments", ['body' => 'hi'])
        ->assertForbidden();
});

test('listing returns top-level comments newest first with preloaded replies', function () {
    $author = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $first = Comment::factory()->create(['post_id' => $post->id, 'profile_id' => $author->personalProfile->id]);
    $second = Comment::factory()->create(['post_id' => $post->id, 'profile_id' => $author->personalProfile->id]);

    // 3 replies on $second; only the first 2 should be preloaded.
    $replies = collect(range(1, 3))->map(fn () => Comment::factory()->create([
        'post_id' => $post->id,
        'profile_id' => $author->personalProfile->id,
        'parent_comment_id' => $second->id,
        'depth' => 1,
    ]));
    $second->increment('replies_count', 3);

    $response = $this->actingAs($author)->getJson("/api/v1/posts/{$post->ulid}/comments")
        ->assertOk()
        ->assertJsonPath('data.0.ulid', $second->ulid) // newest first
        ->assertJsonPath('data.1.ulid', $first->ulid);

    // The preloaded replies collection is capped at 2 per parent.
    expect($response->json('data.0.replies'))->toHaveCount(2)
        ->and($response->json('data.0.replies_count'))->toBe(3)
        ->and($response->json('data.1.replies'))->toHaveCount(0);
});

test('the replies endpoint returns replies oldest first', function () {
    $author = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);
    $parent = Comment::factory()->create(['post_id' => $post->id, 'profile_id' => $author->personalProfile->id]);
    $r1 = Comment::factory()->create(['post_id' => $post->id, 'profile_id' => $author->personalProfile->id, 'parent_comment_id' => $parent->id, 'depth' => 1]);
    $r2 = Comment::factory()->create(['post_id' => $post->id, 'profile_id' => $author->personalProfile->id, 'parent_comment_id' => $parent->id, 'depth' => 1]);

    $this->actingAs($author)->getJson("/api/v1/comments/{$parent->ulid}/replies")
        ->assertOk()
        ->assertJsonPath('data.0.ulid', $r1->ulid)
        ->assertJsonPath('data.1.ulid', $r2->ulid)
        ->assertJsonPath('data.0.parent_comment_ulid', $parent->ulid);
});

test('an author can edit their own comment', function () {
    $author = userWithProfile();
    $comment = Comment::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($author)->patchJson("/api/v1/comments/{$comment->ulid}", ['body' => 'edited'])
        ->assertOk()
        ->assertJsonPath('data.body', 'edited');

    expect($comment->fresh()->body)->toBe('edited');
});

test('a non-author cannot edit a comment', function () {
    $author = userWithProfile();
    $other = userWithProfile();
    $comment = Comment::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($other)->patchJson("/api/v1/comments/{$comment->ulid}", ['body' => 'nope'])
        ->assertForbidden();
});

test('the comment author and the post author can delete, decrementing counters', function () {
    $postAuthor = userWithProfile();
    $commenter = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $postAuthor->personalProfile->id]);
    $comment = Comment::factory()->create(['post_id' => $post->id, 'profile_id' => $commenter->personalProfile->id]);
    $post->increment('comments_count');

    // Post author may delete someone else's comment.
    $this->actingAs($postAuthor)->deleteJson("/api/v1/comments/{$comment->ulid}")->assertNoContent();

    expect($comment->fresh()->trashed())->toBeTrue()
        ->and($post->fresh()->comments_count)->toBe(0);
});

test('an unrelated user cannot delete a comment', function () {
    $author = userWithProfile();
    $stranger = userWithProfile();
    $comment = Comment::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($stranger)->deleteJson("/api/v1/comments/{$comment->ulid}")->assertForbidden();
});

test('a deleted comment with replies renders as a tombstone in the listing', function () {
    $author = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);
    $parent = Comment::factory()->create(['post_id' => $post->id, 'profile_id' => $author->personalProfile->id]);
    Comment::factory()->create(['post_id' => $post->id, 'profile_id' => $author->personalProfile->id, 'parent_comment_id' => $parent->id, 'depth' => 1]);
    $parent->increment('replies_count');
    $post->increment('comments_count', 2);

    $this->actingAs($author)->deleteJson("/api/v1/comments/{$parent->ulid}")->assertNoContent();

    $this->actingAs($author)->getJson("/api/v1/posts/{$post->ulid}/comments")
        ->assertOk()
        ->assertJsonPath('data.0.ulid', $parent->ulid)
        ->assertJsonPath('data.0.body', '[deleted]')
        ->assertJsonPath('data.0.deleted', true);
});

test('a deleted comment without replies disappears from the listing', function () {
    $author = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);
    $comment = Comment::factory()->create(['post_id' => $post->id, 'profile_id' => $author->personalProfile->id]);
    $post->increment('comments_count');

    $this->actingAs($author)->deleteJson("/api/v1/comments/{$comment->ulid}")->assertNoContent();

    $this->actingAs($author)->getJson("/api/v1/posts/{$post->ulid}/comments")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('a CommentAdded event is broadcast on the post channel', function () {
    Event::fake([CommentAdded::class]);

    $author = userWithProfile();
    $post = Post::factory()->create(['profile_id' => $author->personalProfile->id]);

    $this->actingAs($author)->postJson("/api/v1/posts/{$post->ulid}/comments", ['body' => 'hi']);

    Event::assertDispatched(CommentAdded::class, fn (CommentAdded $e) => $e->comment->post->is($post));
});
