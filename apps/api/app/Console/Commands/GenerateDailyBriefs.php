<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Services\Brief\BriefService;
use App\Support\Features;
use Illuminate\Console\Command;

/**
 * Pre-warms today's Daily Business Brief headline for every active member so the
 * Home card loads instantly (the AI call, when configured, runs here rather than
 * on the member's first request). Scheduled daily; safe to re-run — BriefService
 * only generates a headline the first time per member per day.
 */
class GenerateDailyBriefs extends Command
{
    protected $signature = 'briefs:generate';

    protected $description = "Pre-generate today's Daily Business Brief headline for active members";

    public function handle(BriefService $briefs): int
    {
        if (! Features::enabled('daily_brief')) {
            $this->info('Daily Brief is disabled — nothing to prepare.');

            return self::SUCCESS;
        }

        $count = 0;

        Profile::query()
            ->whereHas('user', fn ($q) => $q->whereNull('banned_at'))
            ->each(function (Profile $profile) use ($briefs, &$count) {
                $briefs->forProfile($profile);
                $count++;
            });

        $this->info("Prepared {$count} daily brief(s).");

        return self::SUCCESS;
    }
}
