<?php

namespace App\Http\Resources;

use App\Models\BusinessNeed;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BusinessNeed
 */
class BusinessNeedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'kind' => $this->kind,
            'description' => $this->description,
            'active' => $this->active,
            'business_category' => $this->when(
                $this->relationLoaded('businessCategory') && $this->businessCategory !== null,
                fn () => [
                    'id' => $this->businessCategory->id,
                    'slug' => $this->businessCategory->slug,
                    'name' => $this->businessCategory->name,
                    'icon' => $this->businessCategory->icon,
                ],
            ),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
