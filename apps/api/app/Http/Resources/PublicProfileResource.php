<?php

namespace App\Http\Resources;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The unauthenticated, SEO-facing shape of a profile. Only ever used for public
 * (non-private, non-banned) profiles — the controller enforces that wall before
 * this resource is built. Deliberately minimal: no relationship/viewer state.
 *
 * @mixin Profile
 */
class PublicProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'handle' => $this->handle,
            'name' => $this->name,
            'bio' => $this->bio,
            'avatar_url' => $this->avatarUrl(),
            'cover_url' => $this->coverUrl(),
            'kind' => $this->kind,
            'is_verified' => (bool) $this->is_verified,
            'bio' => $this->bio,
            'social_links' => $this->social_links,
            'website' => $this->website,
            'location' => $this->location,
            'followers_count' => $this->followers_count,
            'posts_count' => $this->posts_count,
            'business_category' => $this->when(
                $this->relationLoaded('businessCategory') && $this->businessCategory !== null,
                fn () => [
                    'slug' => $this->businessCategory->slug,
                    'name' => $this->businessCategory->name,
                ],
            ),
        ];
    }
}
