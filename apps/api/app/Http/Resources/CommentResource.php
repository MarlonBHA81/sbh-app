<?php

namespace App\Http\Resources;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Comment
 */
class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'body' => $this->presentedBody(),
            'depth' => $this->depth,
            'deleted' => $this->trashed(),
            'upvotes_count' => $this->upvotes_count,
            'downvotes_count' => $this->downvotes_count,
            'likes_count' => $this->likes_count,
            'replies_count' => $this->replies_count,
            'liked' => $this->viewerLiked(),
            'my_vote' => $this->viewerVote(),
            'is_helpful' => $this->isHelpful(),
            'parent_comment_ulid' => $this->parent_comment_id === null
                ? null
                : $this->whenLoaded('parent', fn () => $this->parent?->ulid),
            'profile' => new ProfileResource($this->whenLoaded('profile')),
            'created_at' => $this->created_at?->toISOString(),
            'replies' => self::collection($this->whenLoaded('replies')),
        ];
    }
}
