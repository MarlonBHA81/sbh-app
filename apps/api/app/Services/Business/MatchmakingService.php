<?php

namespace App\Services\Business;

use App\Models\BusinessCategory;
use App\Models\BusinessNeed;
use App\Models\Profile;
use App\Services\SafetyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Reciprocal business matchmaking.
 *
 * For each of the viewer's ACTIVE needs we look for other business profiles
 * that have an ACTIVE need of the OPPOSITE kind (offering <-> seeking) in the
 * same business category. Matched profiles are deduplicated and scored:
 *
 *   +3  per category-matched need pair
 *   +2  same city as the viewer
 *   +1  same country as the viewer
 *
 * Own profiles and profiles blocked in either direction are excluded. Results
 * are sorted by score descending and capped. Cached briefly per profile.
 */
class MatchmakingService
{
    public const MAX_RESULTS = 50;

    public const CACHE_TTL_MINUTES = 5;

    private const SCORE_PER_PAIR = 3;

    private const SCORE_SAME_CITY = 2;

    private const SCORE_SAME_COUNTRY = 1;

    public function __construct(private SafetyService $safety) {}

    /**
     * @return list<array{profile: Profile, score: int, matches: list<array<string, mixed>>}>
     */
    public function matches(Profile $viewer): array
    {
        return Cache::remember(
            $this->cacheKey($viewer),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn () => $this->compute($viewer),
        );
    }

    public function forget(Profile $viewer): void
    {
        Cache::forget($this->cacheKey($viewer));
    }

    private function cacheKey(Profile $viewer): string
    {
        return "business:matches:{$viewer->ulid}";
    }

    /**
     * @return list<array{profile: Profile, score: int, matches: list<array<string, mixed>>}>
     */
    private function compute(Profile $viewer): array
    {
        $myNeeds = $viewer->businessNeeds()->active()->with('businessCategory')->get();

        if ($myNeeds->isEmpty()) {
            return [];
        }

        // My own profiles (self across all profiles) plus blocked (both ways).
        $excluded = array_values(array_unique(array_merge(
            $viewer->user->profiles()->pluck('id')->all(),
            $this->safety->blockedProfileIds($viewer),
        )));

        // Distinct (kind, category) pairs I hold, used to build the candidate query.
        $myPairs = $myNeeds->unique(fn (BusinessNeed $n) => $n->kind.':'.$n->business_category_id);

        $candidates = BusinessNeed::query()
            ->active()
            ->whereNotIn('profile_id', $excluded)
            ->whereHas('profile', fn (Builder $profile) => $profile
                ->where('kind', Profile::KIND_BUSINESS)
                ->whereHas('user', fn (Builder $user) => $user->whereNull('banned_at')))
            ->where(function (Builder $query) use ($myPairs) {
                foreach ($myPairs as $need) {
                    $query->orWhere(fn (Builder $inner) => $inner
                        ->where('kind', BusinessNeed::opposite($need->kind))
                        ->where('business_category_id', $need->business_category_id));
                }
            })
            ->with(['businessCategory', 'profile.businessCategory', 'profile.badges'])
            ->get();

        // Group my needs by the key a reciprocal need must satisfy.
        $myByKey = $myNeeds->groupBy(fn (BusinessNeed $n) => $n->kind.':'.$n->business_category_id);

        /** @var array<int, array{profile: Profile, pairs: list<array{my: BusinessNeed, their: BusinessNeed}>}> $byProfile */
        $byProfile = [];

        foreach ($candidates as $their) {
            $myKey = BusinessNeed::opposite($their->kind).':'.$their->business_category_id;
            $myMatches = $myByKey->get($myKey);

            if ($myMatches === null) {
                continue;
            }

            $byProfile[$their->profile_id] ??= ['profile' => $their->profile, 'pairs' => []];

            foreach ($myMatches as $mine) {
                $byProfile[$their->profile_id]['pairs'][] = ['my' => $mine, 'their' => $their];
            }
        }

        $results = [];

        foreach ($byProfile as $data) {
            $profile = $data['profile'];
            $pairs = $data['pairs'];

            $score = count($pairs) * self::SCORE_PER_PAIR;

            if ($viewer->city !== null && $viewer->city === $profile->city) {
                $score += self::SCORE_SAME_CITY;
            }

            if ($viewer->country_code !== null && $viewer->country_code === $profile->country_code) {
                $score += self::SCORE_SAME_COUNTRY;
            }

            $results[] = [
                'profile' => $profile,
                'score' => $score,
                'matches' => array_map(fn (array $pair) => [
                    'my_need' => [
                        'ulid' => $pair['my']->ulid,
                        'kind' => $pair['my']->kind,
                        'category' => $this->categoryArray($pair['my']->businessCategory),
                    ],
                    'their_need' => [
                        'ulid' => $pair['their']->ulid,
                        'kind' => $pair['their']->kind,
                        'description' => $pair['their']->description,
                        'category' => $this->categoryArray($pair['their']->businessCategory),
                    ],
                ], $pairs),
            ];
        }

        usort($results, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, self::MAX_RESULTS);
    }

    /**
     * @return array{id: int, slug: string, name: string, icon: ?string}|null
     */
    private function categoryArray(?BusinessCategory $category): ?array
    {
        if ($category === null) {
            return null;
        }

        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'name' => $category->name,
            'icon' => $category->icon,
        ];
    }
}
