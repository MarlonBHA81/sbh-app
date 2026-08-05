<?php

namespace App\Services\Opportunities;

use App\Models\Opportunity;
use App\Models\Profile;
use Illuminate\Support\Collection;

/**
 * Ranks open opportunities by fit for a member (V3 · deep Coach + personalised
 * Home). Deterministic scoring on the signals we actually hold — industry match,
 * official/sponsored status and how soon it closes — so it needs no AI key and
 * is fully testable. Each returned opportunity carries a `fit_reason`.
 */
class OpportunityFitService
{
    /** How many candidates to score before ranking (bounds the query). */
    private const POOL = 200;

    /**
     * @return Collection<int, Opportunity>
     */
    public function forProfile(?Profile $profile, int $limit = 5): Collection
    {
        $industry = $profile ? mb_strtolower(trim((string) $profile->category)) : '';

        $scored = Opportunity::query()
            ->visible()
            ->limit(self::POOL)
            ->get()
            ->map(function (Opportunity $o) use ($industry) {
                $score = 0;
                $reason = 'Recommended for you';

                $oInd = mb_strtolower(trim((string) $o->industry));
                if ($industry !== '' && $oInd === $industry) {
                    $score += 3;
                    $reason = 'Matches your industry';
                } elseif ($o->industry === null || $oInd === '') {
                    $score += 1;
                }

                $soon = $o->closes_at !== null && $o->closes_at->isBefore(now()->addDays(21));
                if ($soon) {
                    $score += 1;
                    $reason = $reason === 'Recommended for you' ? 'Closing soon' : $reason;
                }

                if ($o->is_official) {
                    $score += 1;
                }
                if ($o->is_sponsored) {
                    $score += 1;
                }

                $o->fit_score = $score;
                $o->fit_reason = $reason;

                return $o;
            });

        // Highest score first, then soonest to close (undated last).
        return $scored
            ->sortBy(fn (Opportunity $o) => sprintf(
                '%02d-%011d',
                max(0, 99 - $o->fit_score),
                $o->closes_at?->timestamp ?? 99999999999,
            ))
            ->take($limit)
            ->values();
    }
}
