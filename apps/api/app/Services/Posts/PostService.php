<?php

namespace App\Services\Posts;

use App\Jobs\RecomputePostScore;
use App\Models\Media;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Topic;
use App\Models\User;
use App\Notifications\PostQuoted;
use App\Notifications\PostReposted;
use App\Services\MentionService;
use App\Support\Geohash;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PostService
{
    public const DEFAULT_TIMEZONE = 'Africa/Johannesburg';

    /** Relations eager-loaded on every post returned to a resource. */
    public const EAGER = ['profile', 'media', 'topics', 'parent.profile', 'parent.media'];

    /** Maximum topics attachable to a single post. */
    public const MAX_TOPICS = 3;

    public function __construct(
        private PostTypeRegistry $registry,
        private MentionService $mentions,
    ) {}

    /**
     * Create a post authored by the given profile from validated data.
     */
    public function create(User $user, Profile $author, array $data): Post
    {
        $type = $data['type'];

        $parent = null;

        if ($this->registry->requiresParent($type)) {
            $parent = $this->resolveParent($author, $data['parent_post_id']);

            if ($type === Post::TYPE_REPOST) {
                $this->ensureNotDuplicateRepost($author, $parent);
            }
        }

        $media = $this->resolveMedia($author, $data['media_ids'] ?? []);

        $payload = $data['payload'] ?? null;

        if ($type === Post::TYPE_MAGNIFIER && ! empty($payload['image_media_id'])) {
            $media = $media->merge(
                $this->resolveMedia($author, [$payload['image_media_id']], 'payload.image_media_id')
            );
        }

        $status = $data['status'] ?? Post::STATUS_PUBLISHED;

        $topics = $this->resolveTopics($data['topic_ids'] ?? []);

        $post = DB::transaction(function () use ($user, $author, $data, $type, $parent, $media, $payload, $status, $topics) {
            $post = Post::create([
                'profile_id' => $author->id,
                'type' => $type,
                'body' => $data['body'] ?? null,
                'payload' => $payload,
                'visibility' => $data['visibility'] ?? Post::VISIBILITY_PUBLIC,
                'status' => $status,
                'scheduled_at' => $status === Post::STATUS_SCHEDULED
                    ? $this->toUtc($data['scheduled_at'], $user)
                    : null,
                'published_at' => $status === Post::STATUS_PUBLISHED ? now() : null,
                'sensitive' => $data['sensitive'] ?? false,
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
                'geohash' => isset($data['lat'], $data['lng'])
                    ? Geohash::encode((float) $data['lat'], (float) $data['lng'])
                    : null,
                'city' => $data['city'] ?? null,
                'country_code' => isset($data['country_code']) ? strtoupper($data['country_code']) : null,
                'parent_post_id' => $parent?->id,
            ]);

            $this->attachMedia($post, $media);

            if ($topics->isNotEmpty()) {
                $post->topics()->attach($topics->pluck('id')->all());
            }

            if ($post->isPublished()) {
                $this->handlePublished($post);
            }

            return $post;
        });

        return $post->load(self::EAGER);
    }

    /**
     * Update a post from validated data. Published posts only accept
     * body/payload/sensitive changes (enforced by UpdatePostRequest).
     */
    public function update(User $user, Post $post, array $data): Post
    {
        DB::transaction(function () use ($user, $post, $data) {
            if ($post->isPublished()) {
                $post->fill(Arr::only($data, ['body', 'payload', 'sensitive']))->save();

                return;
            }

            $post->fill(Arr::only($data, [
                'body', 'payload', 'sensitive', 'visibility', 'lat', 'lng', 'city', 'country_code',
            ]));

            if (array_key_exists('lat', $data) || array_key_exists('lng', $data)) {
                $post->geohash = $post->lat !== null && $post->lng !== null
                    ? Geohash::encode((float) $post->lat, (float) $post->lng)
                    : null;
            }

            if (array_key_exists('media_ids', $data)) {
                $this->replaceMedia($post, $data['media_ids'] ?? []);
            }

            if (isset($data['scheduled_at'])) {
                $post->scheduled_at = $this->toUtc($data['scheduled_at'], $user);
            }

            $post->status = $data['status'] ?? $post->status;

            if ($post->isPublished()) {
                $post->scheduled_at = null;
                $post->published_at = now();
            }

            $post->save();

            if ($post->isPublished()) {
                $this->handlePublished($post);
            }
        });

        return $post->refresh()->load(self::EAGER);
    }

    /**
     * Soft delete a post, rolling back publish-time counters.
     */
    public function delete(Post $post): void
    {
        DB::transaction(function () use ($post) {
            $wasPublished = $post->isPublished();

            $post->delete();

            if (! $wasPublished) {
                return;
            }

            if ($post->profile->posts_count > 0) {
                $post->profile->decrement('posts_count');
            }

            if ($post->isRepost() && $post->parent && $post->parent->reposts_count > 0) {
                $post->parent->decrement('reposts_count');
            }

            Topic::query()
                ->whereIn('id', $post->topics()->pluck('topics.id'))
                ->where('posts_count', '>', 0)
                ->decrement('posts_count');
        });
    }

    /**
     * Publish a draft or scheduled post immediately.
     */
    public function publish(Post $post): Post
    {
        if ($post->isPublished()) {
            return $post;
        }

        DB::transaction(function () use ($post) {
            $post->forceFill([
                'status' => Post::STATUS_PUBLISHED,
                'published_at' => now(),
            ])->save();

            $this->handlePublished($post);
        });

        return $post;
    }

    /**
     * Counter side effects that run exactly once, when a post is published.
     */
    private function handlePublished(Post $post): void
    {
        $post->profile->increment('posts_count');

        if ($post->isRepost() && $post->parent) {
            $post->parent->increment('reposts_count');
        }

        Topic::query()
            ->whereIn('id', $post->topics()->pluck('topics.id'))
            ->increment('posts_count');

        $this->mentions->syncForPost($post);

        $this->notifyParentAuthor($post);

        RecomputePostScore::dispatch($post)->afterCommit();
    }

    /**
     * Notify the parent post's author when this post reposts or quotes it.
     */
    private function notifyParentAuthor(Post $post): void
    {
        if (! in_array($post->type, [Post::TYPE_REPOST, Post::TYPE_QUOTE], true) || ! $post->parent) {
            return;
        }

        $author = $post->profile;
        $recipient = $post->parent->profile;

        if ($recipient->user_id === $author->user_id) {
            return; // never notify on self-action
        }

        $notification = $post->type === Post::TYPE_REPOST
            ? new PostReposted(actor: $author, recipient: $recipient, post: $post->parent)
            : new PostQuoted(
                actor: $author,
                recipient: $recipient,
                post: $post->parent,
                preview: $post->body === null ? null : Str::limit($post->body, 80, ''),
            );

        $recipient->user->notify($notification);
    }

    /**
     * Resolve topic ids or slugs into distinct Topic models (max 3).
     *
     * @param  list<int|string>  $idsOrSlugs
     * @return Collection<int, Topic>
     */
    private function resolveTopics(array $idsOrSlugs): Collection
    {
        if ($idsOrSlugs === []) {
            return collect();
        }

        $topics = collect($idsOrSlugs)
            ->map(function ($idOrSlug) {
                $topic = is_numeric($idOrSlug)
                    ? Topic::query()->find((int) $idOrSlug)
                    : Topic::query()->where('slug', $idOrSlug)->first();

                if (! $topic) {
                    throw ValidationException::withMessages([
                        'topic_ids' => ['One or more topics do not exist.'],
                    ]);
                }

                return $topic;
            })
            ->unique('id')
            ->values();

        if ($topics->count() > self::MAX_TOPICS) {
            throw ValidationException::withMessages([
                'topic_ids' => ['A post may have at most '.self::MAX_TOPICS.' topics.'],
            ]);
        }

        return $topics;
    }

    /**
     * Interpret a wall-clock datetime in the user's timezone and convert to UTC.
     */
    private function toUtc(string $datetime, User $user): Carbon
    {
        return Carbon::parse($datetime, $user->timezone ?: self::DEFAULT_TIMEZONE)->utc();
    }

    private function resolveParent(Profile $author, string $parentUlid): Post
    {
        $parent = Post::query()->where('ulid', $parentUlid)->first();

        if (! $parent || ! $parent->isPublished() || ! $parent->isVisibleTo($author)) {
            throw ValidationException::withMessages([
                'parent_post_id' => ['The parent post does not exist or cannot be referenced.'],
            ]);
        }

        return $parent;
    }

    private function ensureNotDuplicateRepost(Profile $author, Post $parent): void
    {
        $exists = Post::query()
            ->where('profile_id', $author->id)
            ->where('type', Post::TYPE_REPOST)
            ->where('parent_post_id', $parent->id)
            ->exists();

        abort_if($exists, 409, 'You have already reposted this post.');
    }

    /**
     * Resolve media ulids into models owned by the author and not yet
     * attached to any other model, preserving the given order.
     *
     * @param  list<string>  $ulids
     * @return Collection<int, Media>
     */
    private function resolveMedia(Profile $author, array $ulids, string $errorKey = 'media_ids'): Collection
    {
        if ($ulids === []) {
            return collect();
        }

        $media = Media::query()->whereIn('ulid', $ulids)->get()->keyBy('ulid');

        foreach ($ulids as $ulid) {
            $item = $media->get($ulid);

            if (! $item || $item->profile_id !== $author->id) {
                throw ValidationException::withMessages([
                    $errorKey => ['One or more media items do not exist or do not belong to you.'],
                ]);
            }

            if ($item->isAttached()) {
                throw ValidationException::withMessages([
                    $errorKey => ['One or more media items are already attached to another post.'],
                ]);
            }
        }

        return collect($ulids)->map(fn (string $ulid) => $media->get($ulid))->values();
    }

    /**
     * @param  Collection<int, Media>  $media
     */
    private function attachMedia(Post $post, Collection $media): void
    {
        $media->each(function (Media $item, int $index) use ($post) {
            $item->mediable()->associate($post);
            $item->order = $index;
            $item->save();
        });
    }

    /**
     * @param  list<string>  $ulids
     */
    private function replaceMedia(Post $post, array $ulids): void
    {
        $media = $this->resolveMedia($post->profile, $ulids);

        $post->media()->update([
            'mediable_type' => null,
            'mediable_id' => null,
            'order' => 0,
        ]);

        $this->attachMedia($post, $media);
    }
}
