<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FollowRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'state' => $this->state,
            'follower' => new ProfileResource($this->whenLoaded('follower')),
            'created_at' => $this->created_at,
        ];
    }
}
