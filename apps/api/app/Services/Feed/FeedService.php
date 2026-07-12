<?php

namespace App\Services\Feed;

use App\Models\Campaign;
use App\Models\Follow;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Topic;
use App\Models\TopicFollow;
use App\Services\Posts\PostService;
use App\Services\SafetyService;
use App\Support\Geohash;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FeedService
{
    public function __construct(private SafetyService $safety) {}

    public const PER_PAGE = 20;

    /** For-you candidates must have been published within this window. */
    private const FRESH_DAYS = 7;

    /** How many globally top-scored posts are mixed into for-you. */
    private const GLOBAL_TOP = 100;

    /** Maximum ulids remembered per profile in the seen set. */
    private const SEEN_CAP = 500;

    private const SEEN_TTL_HOURS = 24;

    /**
     * Posts from accepted follows of the viewer, plus the viewer's own.
     */
    public function following(Profile $viewer): CursorPaginator
    {
        $authorIds = $this->acceptedFollowingIds($viewer)->push($viewer->id);

        return Post::query()
            ->published()
            ->whereIn('profile_id', $authorIds)
            ->tap(fn (Builder $query) => $this->applySafety($query, $viewer))
            ->with(PostService::EAGER)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);
    }

    /**
     * Score-ranked mix of posts from followed topics, followed profiles and
     * the global top — fresh (< 7 days), public posts only. Posts already
     * served to this profile within 24h are excluded via the seen set.
     */
    public function forYou(Profile $viewer): CursorPaginator
    {
        $followedProfileIds = $this->acceptedFollowingIds($viewer);
        $followedTopicIds = TopicFollow::query()
            ->where('profile_id', $viewer->id)
            ->pluck('topic_id');

        $fresh = fn (Builder $query) => $query
            ->published()
            ->where('visibility', Post::VISIBILITY_PUBLIC)
            ->where('published_at', '>=', now()->subDays(self::FRESH_DAYS));

        $globalTopIds = Post::query()
            ->tap($fresh)
            ->orderByDesc('score')
            ->limit(self::GLOBAL_TOP)
            ->pluck('id');

        $seen = $this->seen($viewer);

        $paginator = Post::query()
            ->tap($fresh)
            ->where(function (Builder $query) use ($followedTopicIds, $followedProfileIds, $viewer, $globalTopIds) {
                $query
                    ->whereIn('profile_id', $followedProfileIds->collect()->push($viewer->id))
                    ->orWhereIn('id', $globalTopIds);

                if ($followedTopicIds->isNotEmpty()) {
                    $query->orWhereHas('topics', fn ($topics) => $topics->whereIn('topics.id', $followedTopicIds));
                }
            })
            ->tap(fn (Builder $query) => $this->applyAuthorPrivacy($query, $viewer, $followedProfileIds))
            ->tap(fn (Builder $query) => $this->applySafety($query, $viewer))
            ->when($seen !== [], fn (Builder $query) => $query->whereNotIn('ulid', $seen))
            ->with(PostService::EAGER)
            ->orderByDesc('score')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);

        $this->markSeen($viewer, array_map(fn (Post $post) => $post->ulid, $paginator->items()));

        $this->injectPromoted($paginator, $viewer);

        return $paginator;
    }

    /**
     * Splice paid promoted posts into a for-you page at fixed slots (index 3,
     * 3+rate, …), up to floor(pageSize / injection_rate) of them, chosen from
     * servable campaigns weighted by remaining budget.
     *
     * Injected posts never enter the seen set (they aren't marked seen) and
     * never anchor the cursor (they are only ever spliced before the final
     * organic item), so pagination and dedupe are unaffected. Promoted posts
     * whose target post is already organically present on the page are skipped.
     */
    private function injectPromoted(CursorPaginator $paginator, Profile $viewer): void
    {
        $rate = max(1, (int) config('ads.injection_rate'));
        $max = intdiv(self::PER_PAGE, $rate);

        if ($max < 1) {
            return;
        }

        $items = collect($paginator->items());
        $presentPostIds = $items->pluck('id')->all();
        $excluded = $this->safety->feedExcludedProfileIds($viewer);

        $campaigns = Campaign::query()
            ->servable()
            ->whereHas('post', function (Builder $query) use ($viewer, $excluded) {
                $query
                    ->published()
                    ->where('visibility', Post::VISIBILITY_PUBLIC)
                    ->where('profile_id', '!=', $viewer->id)
                    ->when($excluded !== [], fn (Builder $q) => $q->whereNotIn('profile_id', $excluded));
            })
            ->with(['post' => fn ($query) => $query->with(PostService::EAGER)])
            ->get()
            ->filter(fn (Campaign $c) => $c->post !== null && ! in_array($c->post->id, $presentPostIds, true))
            ->values();

        if ($campaigns->isEmpty()) {
            return;
        }

        $chosen = $this->pickWeighted($campaigns, $max);

        $result = $items->all();

        foreach ($chosen as $i => $campaign) {
            $position = 3 + $i * $rate;

            // Only inject within the page and never at/after the last slot, so
            // the final organic item continues to anchor the cursor.
            if ($position >= count($result)) {
                continue;
            }

            $post = $campaign->post->markPromoted($campaign->ulid);

            array_splice($result, $position, 0, [$post]);
        }

        $paginator->setCollection(collect($result));
    }

    /**
     * Sample up to $max distinct campaigns weighted by remaining budget.
     *
     * @param  Collection<int, Campaign>  $campaigns
     * @return list<Campaign>
     */
    private function pickWeighted(Collection $campaigns, int $max): array
    {
        $pool = $campaigns->all();
        $chosen = [];

        while (count($chosen) < $max && $pool !== []) {
            $total = array_sum(array_map(fn (Campaign $c) => max(1, $c->remainingCents()), $pool));
            $roll = random_int(1, $total);

            foreach ($pool as $key => $campaign) {
                $roll -= max(1, $campaign->remainingCents());

                if ($roll <= 0) {
                    $chosen[] = $campaign;
                    unset($pool[$key]);
                    $pool = array_values($pool);
                    break;
                }
            }
        }

        return $chosen;
    }

    /**
     * Public posts near a point: geohash prefix scan narrowed by a precise
     * haversine distance filter, newest first.
     */
    public function nearby(Profile $viewer, float $lat, float $lng, float $radiusKm): CursorPaginator
    {
        $prefixes = Geohash::coverRadius($lat, $lng, $radiusKm);

        // Standard haversine (spherical law of cosines) guarded against
        // floating point drift pushing acos() out of [-1, 1]. Works on
        // both SQLite (math functions) and MySQL.
        $haversine = '(6371 * acos(min(1.0, max(-1.0,'
            .' cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?))'
            .' + sin(radians(?)) * sin(radians(lat))))))';

        return Post::query()
            ->published()
            ->where('visibility', Post::VISIBILITY_PUBLIC)
            ->whereNotNull('geohash')
            ->where(function (Builder $query) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    $query->orWhere('geohash', 'like', $prefix.'%');
                }
            })
            // cast(? as real): PDO binds the radius as text and SQLite ranks
            // any number below any text, which would defeat the comparison.
            ->whereRaw("{$haversine} <= cast(? as real)", [$lat, $lng, $lat, $radiusKm])
            ->tap(fn (Builder $query) => $this->applyAuthorPrivacy($query, $viewer))
            ->tap(fn (Builder $query) => $this->applySafety($query, $viewer))
            ->with(PostService::EAGER)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);
    }

    /**
     * Public posts geotagged to the viewer's own locale. Country scope matches
     * on country_code; city scope additionally matches city (within the same
     * country). Callers must ensure the viewer has the relevant fields set.
     */
    public function local(Profile $viewer, string $scope): CursorPaginator
    {
        return Post::query()
            ->published()
            ->where('visibility', Post::VISIBILITY_PUBLIC)
            ->where('country_code', $viewer->country_code)
            ->when($scope === 'city', fn (Builder $query) => $query->where('city', $viewer->city))
            ->tap(fn (Builder $query) => $this->applyAuthorPrivacy($query, $viewer))
            ->tap(fn (Builder $query) => $this->applySafety($query, $viewer))
            ->with(PostService::EAGER)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);
    }

    /**
     * Public posts attached to the topic or any of its descendants.
     */
    public function topic(Profile $viewer, Topic $topic): CursorPaginator
    {
        $topicIds = $topic->selfAndDescendantIds();

        return Post::query()
            ->published()
            ->where('visibility', Post::VISIBILITY_PUBLIC)
            ->whereHas('topics', fn ($topics) => $topics->whereIn('topics.id', $topicIds))
            ->tap(fn (Builder $query) => $this->applyAuthorPrivacy($query, $viewer))
            ->tap(fn (Builder $query) => $this->applySafety($query, $viewer))
            ->with(PostService::EAGER)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->cursorPaginate(self::PER_PAGE);
    }

    /**
     * Posts from private profiles only appear to accepted followers (or the
     * author). Applied on top of the public-visibility filter.
     *
     * @param  Collection<int, int>|null  $followedProfileIds
     */
    private function applyAuthorPrivacy(Builder $query, Profile $viewer, ?Collection $followedProfileIds = null): Builder
    {
        $allowed = ($followedProfileIds ?? $this->acceptedFollowingIds($viewer))
            ->collect()
            ->push($viewer->id);

        return $query->where(function (Builder $inner) use ($allowed) {
            $inner
                ->whereHas('profile', fn ($profile) => $profile->where('is_private', false))
                ->orWhereIn('profile_id', $allowed);
        });
    }

    /**
     * Exclude authors the viewer has blocked (either direction) or muted.
     */
    private function applySafety(Builder $query, Profile $viewer): Builder
    {
        $excluded = $this->safety->feedExcludedProfileIds($viewer);

        return $query->when($excluded !== [], fn (Builder $q) => $q->whereNotIn('profile_id', $excluded));
    }

    /**
     * @return Collection<int, int>
     */
    private function acceptedFollowingIds(Profile $viewer): Collection
    {
        return Follow::query()
            ->where('follower_profile_id', $viewer->id)
            ->where('state', Follow::STATE_ACCEPTED)
            ->pluck('followed_profile_id');
    }

    /**
     * Ulids recently served to this profile in the for-you feed. Stored as
     * a plain capped array so it works on any cache store.
     *
     * @return list<string>
     */
    private function seen(Profile $viewer): array
    {
        return Cache::get($this->seenKey($viewer), []);
    }

    /**
     * @param  list<string>  $ulids
     */
    private function markSeen(Profile $viewer, array $ulids): void
    {
        if ($ulids === []) {
            return;
        }

        $seen = array_values(array_unique([...$this->seen($viewer), ...$ulids]));
        $seen = array_slice($seen, -self::SEEN_CAP);

        Cache::put($this->seenKey($viewer), $seen, now()->addHours(self::SEEN_TTL_HOURS));
    }

    private function seenKey(Profile $viewer): string
    {
        return "seen:{$viewer->ulid}";
    }
}
