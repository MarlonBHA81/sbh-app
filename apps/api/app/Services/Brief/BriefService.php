<?php

namespace App\Services\Brief;

use App\Models\BriefItem;
use App\Models\DailyBrief;
use App\Models\Profile;
use App\Models\Topic;
use App\Models\TopicFollow;
use App\Models\XpLedgerEntry;
use App\Services\Ai\AiGateway;
use App\Services\Ai\CannedBriefIntro;
use App\Support\Features;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Assembles the Daily Business Brief (V2 · Feature 7): a short personalised
 * headline plus a few curated items.
 *
 * Both the headline and the item selection are computed once per member per day
 * and cached in `daily_briefs` (the AI calls run at most once/member/day, via
 * the scheduled `briefs:generate` command or lazily on first read). When AI is
 * enabled and the `ai_curation` flag is on, the items are chosen by the AI from
 * a candidate pool using the member's real activity signals; otherwise they
 * fall back to a simple industry match. Everything degrades safely with no API
 * key.
 */
class BriefService
{
    /** Most items to show on the card. */
    private const MAX_ITEMS = 3;

    /** How many candidates to hand the AI to choose from. */
    private const CANDIDATE_POOL = 12;

    public function __construct(private AiGateway $ai) {}

    /**
     * @return array{headline: string, date: string, items: Collection<int, BriefItem>}
     */
    public function forProfile(Profile $profile): array
    {
        $brief = $this->ensureBrief($profile);

        return [
            'headline' => $brief->headline,
            'date' => now()->toDateString(),
            'items' => $this->resolveItems($profile, $brief),
        ];
    }

    /**
     * Today's cached brief, generating (headline + curated item selection) and
     * persisting it on first read of the day.
     */
    private function ensureBrief(Profile $profile): DailyBrief
    {
        $today = now()->toDateString();

        $existing = DailyBrief::query()
            ->where('profile_id', $profile->id)
            ->whereDate('brief_date', $today)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $items = $this->curateItems($profile);

        $headline = $this->ai->chat(
            $this->systemContext($profile),
            [['role' => 'user', 'content' => 'Write my one-line business brief headline for today.']],
            120,
        ) ?? CannedBriefIntro::generate($profile);

        return DailyBrief::create([
            'profile_id' => $profile->id,
            'brief_date' => $today,
            'headline' => $headline,
            'item_ulids' => $items->pluck('ulid')->all(),
        ]);
    }

    /**
     * The items to show: the cached daily selection, re-checked for visibility
     * (so an admin unpublishing an item mid-day drops it), preserving the
     * curated order. Falls back to a live industry match if the cache is empty
     * or every cached item has since been unpublished.
     *
     * @return Collection<int, BriefItem>
     */
    private function resolveItems(Profile $profile, DailyBrief $brief): Collection
    {
        $ulids = is_array($brief->item_ulids) ? array_values(array_filter($brief->item_ulids)) : [];

        if ($ulids !== []) {
            $items = BriefItem::query()
                ->visible()
                ->whereIn('ulid', $ulids)
                ->get()
                ->sortBy(fn (BriefItem $item) => array_search($item->ulid, $ulids, true))
                ->values();

            if ($items->isNotEmpty()) {
                return $items;
            }
        }

        return $this->industryItems($profile);
    }

    /**
     * Choose today's items for the member: AI-ranked from the candidate pool
     * when AI + the ai_curation flag are on, otherwise the industry match.
     *
     * @return Collection<int, BriefItem>
     */
    private function curateItems(Profile $profile): Collection
    {
        $candidates = $this->candidatePool($profile);

        if ($candidates->isEmpty()) {
            return collect();
        }

        if (Features::enabled('ai_curation') && $this->ai->enabled()) {
            $ranked = $this->ai->rankItems(
                $this->curationContext($profile),
                $candidates->map(fn (BriefItem $item) => [
                    'key' => $item->ulid,
                    'kind' => $item->kind,
                    'title' => $item->title,
                    'summary' => Str::limit((string) $item->body, 160),
                ])->all(),
                self::MAX_ITEMS,
            );

            if ($ranked !== []) {
                $byUlid = $candidates->keyBy('ulid');

                $picked = collect($ranked)
                    ->map(fn (string $ulid) => $byUlid->get($ulid))
                    ->filter()
                    ->take(self::MAX_ITEMS)
                    ->values();

                if ($picked->isNotEmpty()) {
                    return $picked;
                }
            }
        }

        return $candidates->take(self::MAX_ITEMS)->values();
    }

    /**
     * The pool the AI (or the fallback) chooses from: published items targeted
     * at the member's industry plus general (null-industry) items, newest first.
     *
     * @return Collection<int, BriefItem>
     */
    private function candidatePool(Profile $profile): Collection
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
            ->limit(self::CANDIDATE_POOL)
            ->get();
    }

    /**
     * The non-AI fallback ordering: the top of the industry-matched pool.
     *
     * @return Collection<int, BriefItem>
     */
    private function industryItems(Profile $profile): Collection
    {
        return $this->candidatePool($profile)->take(self::MAX_ITEMS)->values();
    }

    /**
     * The system prompt for the headline: who is writing, plus the member's
     * real context. Only fields that are set are included (mirrors CoachService).
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

        return 'You write the SBH Daily Business Brief for small business owners '
            .'(many in South Africa). Reply with a SINGLE short, warm, encouraging '
            .'headline line (max ~20 words) that sets up the member\'s day. No lists, '
            ."no markdown, no quotes. Never invent statistics or specific figures.\n\n".$context;
    }

    /**
     * The per-member context used to rank brief items: profile facts plus real
     * usage signals (followed topics + recent activity types), so the AI can
     * tailor the selection to how this member actually uses the app.
     */
    private function curationContext(Profile $profile): string
    {
        $facts = array_filter([
            $profile->category ? "Industry: {$profile->category}" : null,
            $profile->journey_stage ? 'Journey stage: '.str_replace('_', ' ', $profile->journey_stage) : null,
            ($profile->city ?: $profile->location) ? 'Location: '.($profile->city ?: $profile->location) : null,
        ]);

        $topicIds = TopicFollow::query()
            ->where('profile_id', $profile->id)
            ->pluck('topic_id');

        if ($topicIds->isNotEmpty()) {
            $topics = Topic::query()->whereIn('id', $topicIds)->pluck('name')->all();

            if ($topics !== []) {
                $facts[] = 'Follows topics: '.implode(', ', $topics);
            }
        }

        $actions = XpLedgerEntry::query()
            ->where('profile_id', $profile->id)
            ->latest()
            ->limit(30)
            ->pluck('action_key')
            ->unique()
            ->take(8)
            ->map(fn (string $key) => str_replace('_', ' ', $key))
            ->all();

        if ($actions !== []) {
            $facts[] = 'Recent activity: '.implode(', ', $actions);
        }

        return $facts === []
            ? 'No profile details or activity yet.'
            : implode("\n", $facts);
    }
}
