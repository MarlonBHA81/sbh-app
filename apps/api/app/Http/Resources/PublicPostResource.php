<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The unauthenticated, SEO-facing shape of a post. The controller guarantees
 * the post is published, publicly visible and authored by a public, non-banned
 * profile before this resource is built.
 *
 * Hidden payloads (secret text, magnifier reveal text) are NEVER included here.
 * Sensitive posts are still returned but flagged so the frontend can pick a
 * blurred Open Graph image.
 *
 * @mixin Post
 */
class PublicPostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'type' => $this->type,
            'body' => $this->body,
            'sensitive' => (bool) $this->sensitive,
            'profile' => [
                'handle' => $this->profile->handle,
                'name' => $this->profile->name,
                'avatar_url' => $this->profile->avatarUrl(),
                'is_verified' => (bool) $this->profile->is_verified,
            ],
            'media' => $this->media->map(fn ($media) => [
                'type' => $media->type,
                'url' => $media->url(),
                'thumb_url' => $media->thumbUrl(),
                'width' => $media->width,
                'height' => $media->height,
            ])->values()->all(),
            'likes_count' => $this->likes_count,
            'comments_count' => $this->comments_count,
            'reposts_count' => $this->reposts_count,
            'views_count' => $this->views_count,
            'published_at' => $this->published_at?->toISOString(),
            'topics' => $this->topics->map(fn ($topic) => [
                'slug' => $topic->slug,
                'name' => $topic->name,
            ])->values()->all(),
        ];
    }
}
