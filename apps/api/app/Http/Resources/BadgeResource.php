<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BadgeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'kind' => $this->kind,
            'awarded_at' => $this->whenPivotLoaded('profile_badges', fn () => $this->pivot->awarded_at),
        ];
    }
}
