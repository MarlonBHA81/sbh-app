<?php

namespace App\Http\Resources;

use App\Models\Profile;
use App\Services\SafetyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Profile
 */
class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Profile|null $viewer */
        $viewer = $request->attributes->get('activeProfile');

        $relationship = $this->relationshipStateFor($viewer);
        $viewable = $this->isViewableBy($viewer);

        // A block in either direction hides the profile. We surface it as a
        // private/unavailable shape and never leak that a block exists.
        $blocked = app(SafetyService::class)->isBlockedBetween($viewer, $this->resource);

        $base = [
            'ulid' => $this->ulid,
            'kind' => $this->kind,
            'handle' => $this->handle,
            'name' => $this->name,
            'avatar_path' => $blocked ? null : $this->avatar_path,
            'is_private' => $blocked ? true : $this->is_private,
            'is_verified' => $this->is_verified,
            'relationship' => $relationship,
            'is_muted' => $viewer !== null
                && in_array($this->id, app(SafetyService::class)->mutedProfileIds($viewer), true),
        ];

        $isSelf = $viewer !== null && $viewer->user_id === $this->user_id;

        if ($isSelf) {
            // DM privacy is only ever surfaced to the profile's own account.
            $base['dm_privacy'] = $this->dm_privacy;
        }

        if (! $viewable) {
            return $base + ['limited' => true];
        }

        return $base + [
            'limited' => false,
            'bio' => $this->bio,
            'cover_path' => $this->cover_path,
            'category' => $this->category,
            'website' => $this->website,
            'location' => $this->location,
            'country_code' => $this->country_code,
            'city' => $this->city,
            'followers_count' => $this->followers_count,
            'following_count' => $this->following_count,
            'posts_count' => $this->posts_count,
            'xp_total' => $this->xp_total,
            'badges' => BadgeResource::collection($this->whenLoaded('badges')),
            'created_at' => $this->created_at,
        ];
    }
}
