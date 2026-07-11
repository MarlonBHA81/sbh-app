<?php

namespace App\Services\Engagement;

use App\Events\ReactionUpdated;
use App\Jobs\RecomputePostScore;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Reaction;
use App\Notifications\CommentLiked;
use App\Notifications\PostLiked;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Likes and votes on posts and comments. Likes and votes are independent
 * buckets; within the vote bucket up and down are mutually exclusive.
 */
class ReactionService
{
    /**
     * Idempotent like. Returns true when a new like was created.
     */
    public function like(Profile $actor, Post|Comment $reactable): bool
    {
        $created = DB::transaction(function () use ($actor, $reactable) {
            $reaction = Reaction::query()->firstOrCreate([
                'profile_id' => $actor->id,
                'reactable_type' => $reactable->getMorphClass(),
                'reactable_id' => $reactable->getKey(),
                'bucket' => Reaction::BUCKET_LIKE,
            ], [
                'kind' => Reaction::KIND_LIKE,
            ]);

            if ($reaction->wasRecentlyCreated) {
                $reactable->increment('likes_count');

                return true;
            }

            return false;
        });

        if ($created) {
            $reactable->refresh();
            $this->afterChange($reactable);
            $this->notifyLike($actor, $reactable);
        }

        return $created;
    }

    /**
     * Idempotent unlike. Returns true when a like was removed.
     */
    public function unlike(Profile $actor, Post|Comment $reactable): bool
    {
        $removed = DB::transaction(function () use ($actor, $reactable) {
            $deleted = Reaction::query()
                ->where('profile_id', $actor->id)
                ->where('reactable_type', $reactable->getMorphClass())
                ->where('reactable_id', $reactable->getKey())
                ->where('bucket', Reaction::BUCKET_LIKE)
                ->delete();

            if ($deleted > 0 && $reactable->likes_count > 0) {
                $reactable->decrement('likes_count');

                return true;
            }

            return $deleted > 0;
        });

        if ($removed) {
            $reactable->refresh();
            $this->afterChange($reactable);
        }

        return $removed;
    }

    /**
     * Cast, switch or clear a vote. $value is 1 (up), -1 (down) or 0 (clear).
     */
    public function vote(Profile $actor, Post|Comment $reactable, int $value): void
    {
        $changed = DB::transaction(function () use ($actor, $reactable, $value) {
            $vote = Reaction::query()
                ->where('profile_id', $actor->id)
                ->where('reactable_type', $reactable->getMorphClass())
                ->where('reactable_id', $reactable->getKey())
                ->where('bucket', Reaction::BUCKET_VOTE)
                ->first();

            $current = $vote?->kind; // 'up' | 'down' | null
            $desired = match ($value) {
                1 => Reaction::KIND_UP,
                -1 => Reaction::KIND_DOWN,
                default => null,
            };

            if ($current === $desired) {
                return false;
            }

            // Remove the effect of the current vote.
            if ($current === Reaction::KIND_UP) {
                $this->decrement($reactable, 'upvotes_count');
            } elseif ($current === Reaction::KIND_DOWN) {
                $this->decrement($reactable, 'downvotes_count');
            }

            // Apply the desired vote.
            if ($desired === Reaction::KIND_UP) {
                $reactable->increment('upvotes_count');
            } elseif ($desired === Reaction::KIND_DOWN) {
                $reactable->increment('downvotes_count');
            }

            if ($desired === null) {
                $vote?->delete();
            } elseif ($vote) {
                $vote->update(['kind' => $desired]);
            } else {
                Reaction::query()->create([
                    'profile_id' => $actor->id,
                    'reactable_type' => $reactable->getMorphClass(),
                    'reactable_id' => $reactable->getKey(),
                    'bucket' => Reaction::BUCKET_VOTE,
                    'kind' => $desired,
                ]);
            }

            return true;
        });

        if ($changed) {
            $reactable->refresh();
            $this->afterChange($reactable);
        }
    }

    private function decrement(Model $reactable, string $column): void
    {
        if ($reactable->{$column} > 0) {
            $reactable->decrement($column);
        }
    }

    /**
     * Post-side effects after a counter change: rescore + broadcast (posts
     * only — comments carry no feed score and no public channel).
     */
    private function afterChange(Post|Comment $reactable): void
    {
        if ($reactable instanceof Post) {
            RecomputePostScore::dispatch($reactable)->afterCommit();
            ReactionUpdated::dispatch($reactable);
        }
    }

    private function notifyLike(Profile $actor, Post|Comment $reactable): void
    {
        $author = $reactable->profile;

        if ($author->user_id === $actor->user_id) {
            return; // never notify on self-action
        }

        if ($reactable instanceof Post) {
            $author->user->notify(new PostLiked(
                actor: $actor,
                recipient: $author,
                post: $reactable,
            ));

            return;
        }

        $author->user->notify(new CommentLiked(
            actor: $actor,
            recipient: $author,
            post: $reactable->post,
            comment: $reactable,
            preview: Str::limit($reactable->body, 80, ''),
        ));
    }
}
