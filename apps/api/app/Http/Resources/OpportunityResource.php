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
            'industry' => $this->industry,
            'province' => $this->province,
            'amount' => $this->amount,
            'closes_at' => $this->closes_at?->toDateString(),
            'is_open' => $this->isOpen(),
            'is_saved' => (bool) ($this->is_saved ?? false),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
