<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Services\Ads\CampaignService;
use Illuminate\Console\Command;

class SettleCampaigns extends Command
{
    protected $signature = 'ads:settle';

    protected $description = 'Complete campaigns that have exhausted their budget or passed their end date';

    public function handle(CampaignService $campaigns): int
    {
        $settled = 0;

        Campaign::query()
            ->where('status', Campaign::STATUS_ACTIVE)
            ->where(function ($query) {
                $query
                    ->whereColumn('spent_cents', '>=', 'budget_cents')
                    ->orWhere('ends_at', '<', now());
            })
            ->chunkById(200, function ($batch) use ($campaigns, &$settled) {
                foreach ($batch as $campaign) {
                    if ($campaigns->settle($campaign)) {
                        $settled++;
                    }
                }
            });

        $this->info("Settled {$settled} campaign(s).");

        return self::SUCCESS;
    }
}
