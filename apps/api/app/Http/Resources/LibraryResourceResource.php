<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LibraryResourceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'type' => $this->type,
            'category' => $this->category,
            'title' => $this->title,
            'description' => $this->description,
            'url' => $this->url,
            'industry' => $this->industry,
            'is_saved' => (bool) ($this->is_saved ?? false),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
