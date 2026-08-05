<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'about' => $this->about,
            'brand_color' => $this->brand_color,
            'accent_color' => $this->accent_color,
            'logo_url' => $this->logoUrl(),
            'banner_url' => $this->bannerUrl(),
            'policies' => $this->policies,
            'is_active' => (bool) $this->is_active,
            'products_count' => $this->when(isset($this->products_count), fn () => (int) $this->products_count),
            'owner' => [
                'handle' => $this->profile?->handle,
                'name' => $this->profile?->name,
            ],
        ];
    }
}
