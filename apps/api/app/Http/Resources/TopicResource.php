<?php

namespace App\Http\Resources;

use App\Models\Profile;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Topic
 */
class TopicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Profile|null $viewer */
        $viewer = $request->attributes->get('activeProfile');

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'followers_count' => $this->followers_count,
            'posts_count' => $this->posts_count,
            'is_following' => $this->when($viewer !== null, fn () => $this->isFollowedBy($viewer)),
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
