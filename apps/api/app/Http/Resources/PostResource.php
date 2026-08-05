<?php

namespace App\Http\Resources;

use App\Models\Post;
use App\Models\Profile;
use App\Services\Posts\PostTypeRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Post
 */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_merge($this->baseArray($request), $this->satelliteArray($request));
    }

    private function baseArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'type' => $this->type,
            'body' => $this->body,
            'payload' => $this->presentPayload(),
            'visibility' => $this->visibility,
            'status' => $this->status,
            'sensitive' => $this->sensitive,
            'is_question' => (bool) $this->is_question,
            'is_answered' => $this->answered_at !== null,
            'is_win' => (bool) $this->is_win,
            'likes_count' => $this->likes_count,
            'comments_count' => $this->comments_count,
            'reposts_count' => $this->reposts_count,
            'views_count' => $this->views_count,
            'upvotes_count' => $this->upvotes_count,
            'downvotes_count' => $this->downvotes_count,
            'liked' => $this->viewerLiked(),
            'my_vote' => $this->viewerVote(),
            'promoted' => $this->isPromoted(),
            'campaign_ulid' => $this->promotedCampaignUlid(),
            'lat' => $this->lat,
            'lng' => $this->lng,
            'city' => $this->city,
            'country_code' => $this->country_code,
            'published_at' => $this->published_at?->toISOString(),
            'scheduled_at' => $this->scheduled_at?->toISOString(),
            'profile' => new ProfileResource($this->whenLoaded('profile')),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'topics' => $this->whenLoaded('topics', fn () => $this->topics->map(fn ($topic) => [
                'id' => $topic->id,
                'slug' => $topic->slug,
                'name' => $topic->name,
                'icon' => $topic->icon,
            ])->all()),
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? new self($this->parent) : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * Satellite fragment (poll/quiz/event/job) contributed by the type handler,
     * with viewer-specific state hydrated ahead of time by ViewerSatelliteState.
     */
    private function satelliteArray(Request $request): array
    {
        $handler = app(PostTypeRegistry::class)->handler($this->type);

        if ($handler === null) {
            return [];
        }

        /** @var Profile|null $viewer */
        $viewer = $request->attributes->get('activeProfile');

        return $handler->present($this->resource, $viewer);
    }

    /**
     * Hidden payloads (e.g. secret) are masked until explicitly revealed
     * via POST /posts/{ulid}/reveal.
     */
    private function presentPayload(): ?array
    {
        if (app(PostTypeRegistry::class)->hiddenPayload($this->type)) {
            return ['revealed' => false];
        }

        return $this->payload;
    }
}
