<?php

namespace App\Services\Connections;

use App\Models\Profile;
use App\Services\SafetyService;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Today's Connections" (V1 · CONNECT): people a member should meet, each with
 * a human reason. Prefers same-industry, then nearby, then otherwise notable —
 * excluding self, already-followed and blocked profiles.
 */
class ConnectionSuggestionService
{
    public function __construct(private SafetyService $safety) {}

    /**
     * @return array<int, array{profile: Profile, reason: string}>
     */
    public function suggest(Profile $viewer, int $limit = 6): array
    {
        $excluded = array_values(array_unique(array_merge(
            [$viewer->id],
            $this->safety->blockedProfileIds($viewer),
            $viewer->following()->pluck('profiles.id')->all(),
        )));

        $candidates = Profile::query()
            ->whereNotIn('id', $excluded)
            ->where('is_private', false)
            ->whereHas('user', fn (Builder $user) => $user->whereNull('banned_at'))
            ->with(['badges', 'businessCategory'])
            ->orderByDesc('is_verified')
            ->orderByDesc('followers_count')
            ->limit(max($limit * 4, 24))
            ->get();

        $viewerCity = $this->norm($viewer->city ?: $viewer->location);
        $viewerCategory = $this->norm($viewer->category);
        $viewerCategoryId = $viewer->business_category_id;

        return $candidates
            ->map(function (Profile $profile) use ($viewerCity, $viewerCategory, $viewerCategoryId) {
                $sameIndustry = ($viewerCategoryId !== null && $profile->business_category_id === $viewerCategoryId)
                    || ($viewerCategory !== '' && $this->norm($profile->category) === $viewerCategory);
                $sameCity = $viewerCity !== '' && $this->norm($profile->city ?: $profile->location) === $viewerCity;

                $reason = $sameIndustry ? 'Same industry' : ($sameCity ? 'Near you' : 'New to meet');
                $score = ($sameIndustry ? 4 : 0) + ($sameCity ? 2 : 0) + ($profile->is_verified ? 1 : 0);

                return ['profile' => $profile, 'reason' => $reason, 'score' => $score];
            })
            ->sortByDesc(fn (array $row) => [$row['score'], $row['profile']->followers_count])
            ->take($limit)
            ->map(fn (array $row) => ['profile' => $row['profile'], 'reason' => $row['reason']])
            ->values()
            ->all();
    }

    private function norm(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }
}
