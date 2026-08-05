<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\Rank;
use App\Models\XpAction;
use App\Models\XpLedgerEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GamificationController extends Controller
{
    /** Number of days that count toward the weekly leaderboard. */
    private const WEEKLY_DAYS = 7;

    private const TOP = 50;

    public function leaderboard(Request $request): JsonResponse
    {
        $period = $request->query('period') === 'all' ? 'all' : 'weekly';

        $entries = Cache::remember(
            "gamification:leaderboard:{$period}",
            now()->addMinutes(5),
            fn () => $this->computeLeaderboard($period),
        );

        /** @var Profile|null $viewer */
        $viewer = $request->attributes->get('activeProfile');

        return response()->json([
            'period' => $period,
            'entries' => $entries,
            'me' => $viewer ? $this->viewerRow($period, $viewer, $entries) : null,
        ]);
    }

    public function ranks(): JsonResponse
    {
        $ranks = Rank::query()->orderBy('position')->get()->map(fn (Rank $rank) => [
            'key' => $rank->key,
            'name' => $rank->name,
            'icon' => $rank->icon,
            'min_xp' => $rank->min_xp,
            'position' => $rank->position,
        ]);

        return response()->json(['data' => $ranks]);
    }

    public function xp(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->attributes->get('activeProfile');

        $xpTotal = (int) $profile->xp_total;

        $current = Rank::query()
            ->where('min_xp', '<=', $xpTotal)
            ->orderByDesc('min_xp')
            ->first();

        $next = Rank::query()
            ->where('min_xp', '>', $xpTotal)
            ->orderBy('min_xp')
            ->first();

        return response()->json([
            'xp_total' => $xpTotal,
            'rank' => $current ? [
                'key' => $current->key,
                'name' => $current->name,
                'icon' => $current->icon,
                'min_xp' => $current->min_xp,
            ] : null,
            'next_rank' => $next ? [
                'key' => $next->key,
                'name' => $next->name,
                'icon' => $next->icon,
                'min_xp' => $next->min_xp,
                'progress_pct' => $this->progressPct($xpTotal, $current?->min_xp ?? 0, $next->min_xp),
            ] : null,
            'today' => $this->todayBreakdown($profile),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function computeLeaderboard(string $period): array
    {
        if ($period === 'all') {
            $profiles = Profile::query()
                ->orderByDesc('xp_total')
                ->orderBy('id')
                ->limit(self::TOP)
                ->get();

            return $profiles->values()->map(fn (Profile $p, int $i) => [
                'position' => $i + 1,
                'xp' => (int) $p->xp_total,
                'profile' => $this->liteProfile($p),
            ])->all();
        }

        $sums = XpLedgerEntry::query()
            ->where('created_at', '>=', now()->subDays(self::WEEKLY_DAYS))
            ->groupBy('profile_id')
            ->select('profile_id', DB::raw('SUM(points) as xp'))
            ->orderByDesc('xp')
            ->orderBy('profile_id')
            ->limit(self::TOP)
            ->get();

        $profiles = Profile::query()
            ->whereIn('id', $sums->pluck('profile_id'))
            ->get()
            ->keyBy('id');

        return $sums->values()
            ->filter(fn ($row) => $profiles->has($row->profile_id))
            ->values()
            ->map(fn ($row, int $i) => [
                'position' => $i + 1,
                'xp' => (int) $row->xp,
                'profile' => $this->liteProfile($profiles[$row->profile_id]),
            ])->all();
    }

    /**
     * The viewer's own leaderboard row, reusing the top-list position when the
     * viewer already appears there and computing it separately otherwise.
     *
     * @param  list<array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    private function viewerRow(string $period, Profile $viewer, array $entries): array
    {
        foreach ($entries as $entry) {
            if ($entry['profile']['ulid'] === $viewer->ulid) {
                return $entry;
            }
        }

        if ($period === 'all') {
            $xp = (int) $viewer->xp_total;
            $position = Profile::query()->where('xp_total', '>', $xp)->count() + 1;
        } else {
            $xp = (int) XpLedgerEntry::query()
                ->where('profile_id', $viewer->id)
                ->where('created_at', '>=', now()->subDays(self::WEEKLY_DAYS))
                ->sum('points');

            $position = XpLedgerEntry::query()
                ->where('created_at', '>=', now()->subDays(self::WEEKLY_DAYS))
                ->groupBy('profile_id')
                ->havingRaw('SUM(points) > ?', [$xp])
                ->get()
                ->count() + 1;
        }

        return [
            'position' => $position,
            'xp' => $xp,
            'profile' => $this->liteProfile($viewer),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function todayBreakdown(Profile $profile): array
    {
        $today = XpLedgerEntry::query()
            ->where('profile_id', $profile->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->groupBy('action_key')
            ->select('action_key', DB::raw('COUNT(*) as times'), DB::raw('SUM(points) as earned'))
            ->get()
            ->keyBy('action_key');

        return XpAction::query()
            ->where('enabled', true)
            ->orderBy('id')
            ->get()
            ->map(fn (XpAction $action) => [
                'action_key' => $action->key,
                'label' => $action->label,
                'points' => $action->points,
                'daily_cap' => $action->daily_cap,
                'times_today' => (int) ($today[$action->key]->times ?? 0),
                'earned_today' => (int) ($today[$action->key]->earned ?? 0),
            ])->all();
    }

    private function progressPct(int $xpTotal, int $currentMin, int $nextMin): float
    {
        $span = $nextMin - $currentMin;

        if ($span <= 0) {
            return 100.0;
        }

        $pct = ($xpTotal - $currentMin) / $span * 100;

        return round(max(0.0, min(100.0, $pct)), 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function liteProfile(Profile $profile): array
    {
        return [
            'ulid' => $profile->ulid,
            'handle' => $profile->handle,
            'name' => $profile->name,
            'avatar_url' => $profile->avatarUrl(),
            'xp_total' => (int) $profile->xp_total,
        ];
    }
}
