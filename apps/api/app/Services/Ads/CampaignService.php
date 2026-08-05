<?php

namespace App\Services\Ads;

use App\Models\Campaign;
use App\Models\Post;
use App\Models\Profile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Lifecycle of promoted-post campaigns: creation, pause/resume, early end and
 * settlement (marking exhausted or expired campaigns completed).
 */
class CampaignService
{
    /**
     * Create an active campaign promoting one of the profile's own posts.
     *
     * @param  array{budget_cents?: int|null, duration_days: int}  $data
     */
    public function create(Profile $advertiser, Post $post, array $data): Campaign
    {
        if ($post->profile_id !== $advertiser->id) {
            throw ValidationException::withMessages([
                'post_ulid' => ['You can only promote your own posts.'],
            ]);
        }

        if (! $post->isPublished() || $post->visibility !== Post::VISIBILITY_PUBLIC) {
            throw ValidationException::withMessages([
                'post_ulid' => ['Only published, public posts can be promoted.'],
            ]);
        }

        $exists = Campaign::query()
            ->where('post_id', $post->id)
            ->where('status', '!=', Campaign::STATUS_COMPLETED)
            ->exists();

        abort_if($exists, 409, 'This post already has an active campaign.');

        $now = now();

        return Campaign::create([
            'profile_id' => $advertiser->id,
            'post_id' => $post->id,
            'status' => Campaign::STATUS_ACTIVE,
            'budget_cents' => isset($data['budget_cents']) ? (int) $data['budget_cents'] : null,
            'spent_cents' => 0,
            'cpi_cents' => (int) config('ads.cpi_cents'),
            'starts_at' => $now,
            'ends_at' => (clone $now)->addDays((int) $data['duration_days']),
        ]);
    }

    /**
     * Set a campaign to active or paused. Completed campaigns are terminal.
     */
    public function setStatus(Campaign $campaign, string $status): Campaign
    {
        if ($campaign->isCompleted()) {
            throw ValidationException::withMessages([
                'status' => ['A completed campaign cannot be changed.'],
            ]);
        }

        $campaign->update(['status' => $status]);

        return $campaign;
    }

    /**
     * End a campaign early by marking it completed.
     */
    public function end(Campaign $campaign): Campaign
    {
        if (! $campaign->isCompleted()) {
            $campaign->update(['status' => Campaign::STATUS_COMPLETED]);
        }

        return $campaign;
    }

    /**
     * Complete the campaign if it has exhausted its budget or passed its end
     * date. Returns true when the status transitioned to completed.
     */
    public function settle(Campaign $campaign): bool
    {
        if ($campaign->isCompleted()) {
            return false;
        }

        $exhausted = $campaign->budget_cents !== null
            && $campaign->spent_cents >= $campaign->budget_cents;
        $expired = $campaign->ends_at !== null && Carbon::now()->greaterThan($campaign->ends_at);

        if ($exhausted || $expired) {
            $campaign->update(['status' => Campaign::STATUS_COMPLETED]);

            return true;
        }

        return false;
    }
}
