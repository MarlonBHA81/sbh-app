<?php

namespace App\Services\Connections;

use App\Models\Profile;
use App\Services\SafetyService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Mentor matching (V2 · CONNECT): opted-in mentors ranked for a member by
 * relevance — same industry first, then a related journey stage, then verified
 * and well-connected. Mirrors ConnectionSuggestionService. Excludes self,
 * already-followed and blocked profiles.
 */
class MentorSuggestionService
{
    public function __construct(private SafetyService $safety) {}

    /**
     * @return array<int, array{profile: Profile, reason: string}>
     */
    public function suggest(Profile $viewer, int $limit = 20): array
    {
        $excluded = array_values(array_unique(array_merge(
            [$viewer->id],
            $this->safety->blockedProfileIds($viewer),
        )));

        $candidates = Profile::query()
            ->where('is_mentor', true)
            ->whereNotIn('id', $excluded)
            ->where('is_private', false)
            ->whereHas('user', fn (Builder $user) => $user->whereNull('banned_at'))
            ->with(['badges', 'businessCategory'])
            ->orderByDesc('is_verified')
            ->orderByDesc('followers_count')
            ->limit(max($limit * 4, 40))
            ->get();

        $viewerCategory = $this->norm($viewer->category);
        $viewerCategoryId = $viewer->business_category_id;
        $viewerStage = $viewer->journey_stage;

        return $candidates
            ->map(function (Profile $profile) use ($viewerCategory, $viewerCategoryId, $viewerStage) {
                $sameIndustry = ($viewerCategoryId !== null && $profile->business_category_id === $viewerCategoryId)
                    || ($viewerCategory !== '' && $this->norm($profile->category) === $viewerCategory);
                $sameStage = $viewerStage !== null && $profile->journey_stage === $viewerStage;

                $reason = $sameIndustry
                    ? 'Mentors in your industry'
                    : ($sameStage ? 'Been where you are' : 'Ready to help');
                $score = ($sameIndustry ? 4 : 0) + ($sameStage ? 2 : 0) + ($profile->is_verified ? 1 : 0);

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
