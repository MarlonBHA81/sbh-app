<?php

namespace App\Services\Gamification;

use App\Models\Challenge;
use App\Models\Profile;
use App\Models\XpLedgerEntry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Challenge leaderboards: participants ranked by XP earned inside the
 * challenge window. Reuses the xp_ledger so every existing XP action
 * (posting, engagement received, etc.) counts automatically.
 */
class ChallengeService
{
    private const TOP_LIMIT = 50;

    private const CACHE_SECONDS = 300;

    public function join(Challenge $challenge, Profile $profile): void
    {
        abort_if($challenge->hasEnded(), 422, 'This challenge has ended.');

        $challenge->participants()->syncWithoutDetaching([
            $profile->id => ['joined_at' => now()],
        ]);
    }

    public function leave(Challenge $challenge, Profile $profile): void
    {
        $challenge->participants()->detach($profile->id);
    }

    /**
     * Top participants by XP earned within the challenge window.
     *
     * @return list<array{profile: Profile, points: int, rank: int}>
     */
    public function leaderboard(Challenge $challenge): array
    {
        return Cache::remember(
            "challenge:leaderboard:{$challenge->id}",
            self::CACHE_SECONDS,
            function () use ($challenge) {
                $rows = $this->scoresQuery($challenge)
                    ->orderByDesc('points')
                    ->orderBy('challenge_participants.joined_at')
                    ->limit(self::TOP_LIMIT)
                    ->get();

                $profiles = Profile::query()
                    ->whereIn('id', $rows->pluck('profile_id'))
                    ->get()
                    ->keyBy('id');

                // Drop rows whose profile is gone (soft-deleted / removed) so a
                // stale participant row can't null-deref into the serializer.
                return $rows
                    ->filter(fn ($row) => $profiles->has($row->profile_id))
                    ->values()
                    ->map(fn ($row, $index) => [
                        'profile' => $profiles[$row->profile_id],
                        'points' => (int) $row->points,
                        'rank' => $index + 1,
                    ])->all();
            },
        );
    }

    /**
     * The viewer's own points and rank (null when not participating).
     *
     * @return array{points: int, rank: int}|null
     */
    public function viewerRow(Challenge $challenge, Profile $profile): ?array
    {
        $joined = $challenge->participants()
            ->where('profiles.id', $profile->id)
            ->exists();

        if (! $joined) {
            return null;
        }

        $points = (int) XpLedgerEntry::query()
            ->where('profile_id', $profile->id)
            ->whereBetween('created_at', [$challenge->starts_at, $challenge->ends_at])
            ->sum('points');

        $ahead = $this->scoresQuery($challenge)
            ->havingRaw('points > ?', [$points])
            ->get()
            ->count();

        return ['points' => $points, 'rank' => $ahead + 1];
    }

    private function scoresQuery(Challenge $challenge)
    {
        // LEFT JOIN so zero-XP participants still appear on the board.
        return DB::table('challenge_participants')
            ->where('challenge_participants.challenge_id', $challenge->id)
            ->leftJoin('xp_ledger', function ($join) use ($challenge) {
                $join->on('xp_ledger.profile_id', '=', 'challenge_participants.profile_id')
                    ->whereBetween('xp_ledger.created_at', [$challenge->starts_at, $challenge->ends_at]);
            })
            ->groupBy('challenge_participants.profile_id', 'challenge_participants.joined_at')
            ->select('challenge_participants.profile_id')
            ->selectRaw('COALESCE(SUM(xp_ledger.points), 0) as points');
    }
}
