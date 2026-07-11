<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Mention;
use App\Models\Post;
use App\Models\Profile;
use App\Notifications\Mentioned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Parses @handle mentions out of post/comment bodies, records mention rows
 * and notifies each mentioned profile. Self-mentions and unknown handles are
 * silently ignored.
 */
class MentionService
{
    /** Handles are 3-30 chars of [a-z0-9_]; matched case-insensitively. */
    private const PATTERN = '/@([a-z0-9_]{3,30})/i';

    public function syncForPost(Post $post): void
    {
        $this->sync($post, $post->body, $post->profile, $post->body);
    }

    public function syncForComment(Comment $comment): void
    {
        $this->sync($comment, $comment->body, $comment->profile, $comment->body);
    }

    /**
     * @return Collection<int, Profile> the profiles that were mentioned
     */
    private function sync(Model $mentionable, ?string $text, Profile $author, ?string $preview): Collection
    {
        $handles = $this->parseHandles($text);

        if ($handles->isEmpty()) {
            return collect();
        }

        $profiles = Profile::query()
            ->whereIn('handle', $handles->all())
            ->get()
            ->reject(fn (Profile $profile) => $profile->id === $author->id) // skip self-mention
            ->values();

        $previewText = $preview === null ? null : Str::limit($preview, 80, '');

        foreach ($profiles as $profile) {
            $mention = Mention::query()->firstOrCreate([
                'mentionable_type' => $mentionable->getMorphClass(),
                'mentionable_id' => $mentionable->getKey(),
                'mentioned_profile_id' => $profile->id,
            ], [
                'mentioner_profile_id' => $author->id,
            ]);

            if (! $mention->wasRecentlyCreated) {
                continue;
            }

            $profile->user->notify(new Mentioned(
                actor: $author,
                recipient: $profile,
                post: $mentionable instanceof Post ? $mentionable : ($mentionable instanceof Comment ? $mentionable->post : null),
                comment: $mentionable instanceof Comment ? $mentionable : null,
                preview: $previewText,
            ));
        }

        return $profiles;
    }

    /**
     * @return Collection<int, string> distinct lowercased handles
     */
    private function parseHandles(?string $text): Collection
    {
        if ($text === null || $text === '') {
            return collect();
        }

        preg_match_all(self::PATTERN, $text, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $handle) => mb_strtolower($handle))
            ->unique()
            ->values();
    }
}
