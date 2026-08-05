<?php

namespace App\Http\Resources;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin Campaign
 */
class CampaignResource extends JsonResource
{
    /**
     * Optional per-day event series, attached by the controller when
     * ?series=1 is requested.
     *
     * @var list<array{date: string, impressions: int, clicks: int, link_clicks: int}>|null
     */
    public ?array $series = null;

    /** Unique profiles reached (distinct impression viewers); detail only. */
    public ?int $reach = null;

    public function withSeries(array $series): static
    {
        $this->series = $series;

        return $this;
    }

    public function withReach(int $reach): static
    {
        $this->reach = $reach;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'ulid' => $this->ulid,
            'status' => $this->status,
            'budget_cents' => $this->budget_cents,
            'spent_cents' => $this->spent_cents,
            'remaining_cents' => $this->remainingCents(),
            'cpi_cents' => $this->cpi_cents,
            'impressions' => $this->impressions_count,
            'clicks' => $this->clicks_count,
            'link_clicks' => $this->link_clicks_count,
            'ctr_pct' => $this->ctrPct(),
            'link_ctr_pct' => $this->linkCtrPct(),
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'post' => $this->whenLoaded('post', fn () => $this->postLite()),
            'created_at' => $this->created_at?->toISOString(),
        ];

        if ($this->series !== null) {
            $data['series'] = $this->series;
        }

        if ($this->reach !== null) {
            $data['reach'] = $this->reach;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function postLite(): ?array
    {
        if ($this->post === null) {
            return null;
        }

        return [
            'ulid' => $this->post->ulid,
            'type' => $this->post->type,
            'body' => $this->post->body === null ? null : Str::limit($this->post->body, 140),
            // Engagement on the promoted post — what advertisers care about.
            'likes_count' => (int) $this->post->likes_count,
            'comments_count' => (int) $this->post->comments_count,
            'reposts_count' => (int) $this->post->reposts_count,
            'views_count' => (int) $this->post->views_count,
        ];
    }
}
