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
            'avatar_url' => $blocked ? null : $this->avatarUrl(),
            'is_private' => $blocked ? true : $this->is_private,
            'is_verified' => $this->is_verified,
            'cipc_verified' => $this->cipc_verified_at !== null,
            'relationship' => $relationship,
            'is_muted' => $viewer !== null
                && in_array($this->id, app(SafetyService::class)->mutedProfileIds($viewer), true),
        ];

        $isSelf = $viewer !== null && $viewer->user_id === $this->user_id;

        if ($isSelf) {
            // DM privacy is only ever surfaced to the profile's own account.
            $base['dm_privacy'] = $this->dm_privacy;
            $base['share_location'] = (bool) $this->share_location;
        }

        if (! $viewable) {
            return $base + ['limited' => true];
        }

        return $base + [
            'limited' => false,
            'bio' => $this->bio,
            'cover_url' => $this->coverUrl(),
            'category' => $this->category,
            'registration_number' => $this->registration_number,
            'cipc_registered_name' => $this->cipc_registered_name,
            'journey_stage' => $this->journey_stage,
            'is_mentor' => (bool) $this->is_mentor,
            'is_facilitator' => (bool) $this->is_facilitator,
            // The viewer's role on this profile (Roles P4); only set on /me.
            'my_role' => $this->my_role ?? null,
            'business_category' => $this->when(
                $this->relationLoaded('businessCategory') && $this->businessCategory !== null,
                fn () => [
                    'id' => $this->businessCategory->id,
                    'slug' => $this->businessCategory->slug,
                    'name' => $this->businessCategory->name,
                    'icon' => $this->businessCategory->icon,
                ],
            ),
            'website' => $this->website,
            'social_links' => $this->social_links,
            'location' => $this->location,
            'country_code' => $this->country_code,
            'city' => $this->city,
            'followers_count' => $this->followers_count,
            'following_count' => $this->following_count,
            'posts_count' => $this->posts_count,
            'xp_total' => $this->xp_total,
            'helpful_count' => $this->helpful_count,
            'badges' => BadgeResource::collection($this->whenLoaded('badges')),
            'created_at' => $this->created_at,
        ];
    }
}
