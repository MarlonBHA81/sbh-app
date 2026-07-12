<?php

namespace App\Services\Business;

use App\Models\Post;
use App\Models\Profile;
use App\Services\Posts\PostService;
use App\Services\SafetyService;
use App\Support\Geohash;
use App\Support\Sql;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

class BusinessEventService
{
    public const PER_PAGE = 20;

    public function __construct(private SafetyService $safety) {}

    /**
     * Published, public event posts joined to their event satellite.
     *
     * upcoming: starts_at >= now, soonest first.
     * past:     starts_at <  now, most recent first.
     *
     * An optional geo radius filters on the post's own coordinates.
     */
    public function events(
        Profile $viewer,
        string $filter,
        ?float $lat = null,
        ?float $lng = null,
        ?float $radiusKm = null,
    ): CursorPaginator {
        $upcoming = $filter === 'upcoming';
        $direction = $upcoming ? 'asc' : 'desc';

        $query = Post::query()
            ->published()
            ->where('posts.type', Post::TYPE_EVENT)
            ->where('posts.visibility', Post::VISIBILITY_PUBLIC)
            ->join('events', 'events.post_id', '=', 'posts.id')
            ->when(
                $upcoming,
                fn (Builder $q) => $q->where('events.starts_at', '>=', now()),
                fn (Builder $q) => $q->where('events.starts_at', '<', now()),
            )
            ->tap(fn (Builder $q) => $this->applyRadius($q, $lat, $lng, $radiusKm))
            ->tap(fn (Builder $q) => $this->applySafety($q, $viewer))
            ->with(PostService::EAGER)
            ->select('posts.*')
            ->orderBy('events.starts_at', $direction)
            ->orderBy('posts.id', $direction);

        return $query->cursorPaginate(self::PER_PAGE);
    }

    private function applyRadius(Builder $query, ?float $lat, ?float $lng, ?float $radiusKm): Builder
    {
        if ($lat === null || $lng === null || $radiusKm === null) {
            return $query;
        }

        $prefixes = Geohash::coverRadius($lat, $lng, $radiusKm);

        $haversine = Sql::haversine('posts.lat', 'posts.lng');

        return $query
            ->whereNotNull('posts.geohash')
            ->where(function (Builder $inner) use ($prefixes) {
                foreach ($prefixes as $prefix) {
                    $inner->orWhere('posts.geohash', 'like', $prefix.'%');
                }
            })
            ->whereRaw("{$haversine} <= cast(? as real)", [$lat, $lng, $lat, $radiusKm]);
    }

    private function applySafety(Builder $query, Profile $viewer): Builder
    {
        $excluded = $this->safety->feedExcludedProfileIds($viewer);

        return $query->when(
            $excluded !== [],
            fn (Builder $q) => $q->whereNotIn('posts.profile_id', $excluded),
        );
    }
}
