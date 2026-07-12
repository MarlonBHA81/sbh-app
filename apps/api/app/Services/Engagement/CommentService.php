<?php

namespace App\Services\Engagement;

use App\Events\CommentAdded;
use App\Jobs\RecomputePostScore;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Profile;
use App\Notifications\CommentReplied;
use App\Notifications\PostCommented;
use App\Services\Analytics\PostStatsService;
use App\Services\Gamification\GamificationService;
use App\Services\MentionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommentService
{
    public function __construct(
        private MentionService $mentions,
        private GamificationService $gamification,
        private PostStatsService $stats,
    ) {}

    /**
     * Create a comment (or reply) authored by $author on $post.
     *
     * @param  array{body: string, parent_comment_id?: string|null}  $data
     */
    public function create(Profile $author, Post $post, array $data): Comment
    {
        $parent = null;

        if (! empty($data['parent_comment_id'])) {
            $parent = Comment::query()
                ->where('ulid', $data['parent_comment_id'])
                ->where('post_id', $post->id)
                ->first();

            if (! $parent) {
                throw ValidationException::withMessages([
                    'parent_comment_id' => ['The parent comment does not exist on this post.'],
                ]);
            }
        }

        $depth = $parent ? $parent->depth + 1 : 0;

        if ($depth > Comment::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'parent_comment_id' => ['Replies cannot be nested deeper than '.Comment::MAX_DEPTH.' levels.'],
            ]);
        }

        $comment = DB::transaction(function () use ($author, $post, $data, $parent, $depth) {
            $comment = Comment::query()->create([
                'post_id' => $post->id,
                'profile_id' => $author->id,
                'parent_comment_id' => $parent?->id,
                'body' => $data['body'],
                'depth' => $depth,
            ]);

            // comments_count aggregates every comment (top-level + replies).
            $post->increment('comments_count');

            if ($parent) {
                $parent->increment('replies_count');
            }

            return $comment;
        });

        $comment->setRelation('post', $post);
        $comment->setRelation('profile', $author);
        if ($parent) {
            $comment->setRelation('parent', $parent);
        }

        RecomputePostScore::dispatch($post)->afterCommit();

        $this->mentions->syncForComment($comment);

        $this->notify($author, $post, $parent, $comment);

        $this->gamification->award($author, GamificationService::COMMENT_CREATED, $comment);

        $this->stats->bump($post, PostStatsService::COMMENTS);

        CommentAdded::dispatch($comment);

        return $comment;
    }

    /**
     * Update a comment's body. Authorization is enforced by the caller.
     */
    public function update(Comment $comment, string $body): Comment
    {
        $comment->update(['body' => $body]);

        return $comment;
    }

    /**
     * Soft-delete a comment and roll back its counters. The row is kept so
     * surviving replies stay reachable; the resource renders a tombstone.
     */
    public function delete(Comment $comment): void
    {
        DB::transaction(function () use ($comment) {
            $comment->delete();

            $post = $comment->post;
            if ($post && $post->comments_count > 0) {
                $post->decrement('comments_count');
            }

            $parent = $comment->parent;
            if ($parent && $parent->replies_count > 0) {
                $parent->decrement('replies_count');
            }
        });

        if ($comment->post) {
            RecomputePostScore::dispatch($comment->post)->afterCommit();
        }
    }

    private function notify(Profile $author, Post $post, ?Comment $parent, Comment $comment): void
    {
        $preview = Str::limit($comment->body, 80, '');

        // Notify the post author of a new comment (skip self-action).
        $postAuthor = $post->profile;
        if ($postAuthor->user_id !== $author->user_id) {
            $postAuthor->user->notify(new PostCommented(
                actor: $author,
                recipient: $postAuthor,
                post: $post,
                comment: $comment,
                preview: $preview,
            ));
        }

        // Notify the parent comment's author of a reply (skip self-action).
        if ($parent) {
            $parentAuthor = $parent->profile;
            if ($parentAuthor->user_id !== $author->user_id) {
                $parentAuthor->user->notify(new CommentReplied(
                    actor: $author,
                    recipient: $parentAuthor,
                    post: $post,
                    comment: $comment,
                    preview: $preview,
                ));
            }
        }
    }
}
