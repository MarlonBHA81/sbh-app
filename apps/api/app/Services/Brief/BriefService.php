<?php

namespace App\Services\Brief;

use App\Models\BriefItem;
use App\Models\DailyBrief;
use App\Models\Profile;
use App\Services\Ai\AiGateway;
use App\Services\Ai\CannedBriefIntro;
use Illuminate\Support\Collection;

/**
 * Assembles the Daily Business Brief (V2 · Feature 7): a short personalised
 * headline plus a few admin-curated items matched to the member's industry.
 *
 * The headline is generated once per member per day (AiGateway with a canned
 * fallback so it works with no API key) and cached in `daily_briefs`; the items
 * are read live so admin edits show immediately.
 */
class BriefService
{
    /** Most items to show on the card. */
    private const MAX_ITEMS = 3;

    public function __construct(private AiGateway $ai) {}

    /**
     * @return array{headline: string, date: string, items: Collection<int, BriefItem>}
     */
    public function forProfile(Profile $profile): array
    {
        return [
            'headline' => $this->headlineFor($profile),
            'date' => now()->toDateString(),
            'items' => $this->itemsFor($profile),
        ];
    }

    /**
     * Published items targeted at the member's industry, plus general
     * (null-industry) items, newest first, capped.
     *
     * @return Collection<int, BriefItem>
     */
    private function itemsFor(Profile $profile): Collection
    {
        $industry = trim((string) $profile->category);

        return BriefItem::query()
            ->visible()
            ->when($industry !== '', function ($query) use ($industry) {
                $query->where(function ($q) use ($industry) {
                    $q->whereNull('industry')->orWhere('industry', $industry);
                });
            }, function ($query) {
                $query->whereNull('industry');
            })
            ->latest('published_at')
            ->limit(self::MAX_ITEMS)
            ->get();
    }

    /**
     * Today's cached headline, generating and persisting it on first read.
     */
    private function headlineFor(Profile $profile): string
    {
        $today = now()->toDateString();

        $existing = DailyBrief::query()
            ->where('profile_id', $profile->id)
            ->whereDate('brief_date', $today)
            ->first();

        if ($existing !== null) {
            return $existing->headline;
        }

        $headline = $this->ai->chat(
            $this->systemContext($profile),
            [['role' => 'user', 'content' => 'Write my one-line business brief headline for today.']],
            120,
        ) ?? CannedBriefIntro::generate($profile);

        return DailyBrief::create([
            'profile_id' => $profile->id,
            'brief_date' => $today,
            'headline' => $headline,
        ])->headline;
    }

    /**
     * The system prompt: who is writing, plus the member's real context. Only
     * fields that are set are included (mirrors CoachService).
     */
    private function systemContext(Profile $profile): string
    {
        $facts = array_filter([
            $profile->name ? "Name: {$profile->name}" : null,
            $profile->category ? "Business / industry: {$profile->category}" : null,
            $profile->journey_stage ? 'Journey stage: '.str_replace('_', ' ', $profile->journey_stage) : null,
            ($profile->city ?: $profile->location) ? 'Location: '.($profile->city ?: $profile->location) : null,
        ]);

        $context = $facts === []
            ? 'The member has not shared profile details yet.'
            : "About the member:\n- ".implode("\n- ", $facts);

        return "You write the SBH Daily Business Brief for small business owners "
            ."(many in South Africa). Reply with a SINGLE short, warm, encouraging "
            ."headline line (max ~20 words) that sets up the member's day. No lists, "
            ."no markdown, no quotes. Never invent statistics or specific figures.\n\n".$context;
    }
}
