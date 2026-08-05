<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'type' => $this->type,
            'title' => $this->title,
            'description' => $this->description,
            'organisation' => $this->organisation,
            'url' => $this->url,
            'source' => $this->source,
            'source_url' => $this->source_url,
            'is_official' => (bool) $this->is_official,
            'is_sponsored' => (bool) $this->is_sponsored,
            'sponsor_name' => $this->sponsor_name,
            'sponsor_url' => $this->sponsor_url,
            'industry' => $this->industry,
            'province' => $this->province,
            'amount' => $this->amount,
            'closes_at' => $this->closes_at?->toDateString(),
            'is_open' => $this->isOpen(),
            'is_saved' => (bool) ($this->is_saved ?? false),
            // Why it was surfaced (V3 · fit ranking); only set by OpportunityFitService.
            'fit_reason' => $this->fit_reason ?? null,
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
