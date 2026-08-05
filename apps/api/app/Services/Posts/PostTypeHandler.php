<?php

namespace App\Services\Posts;

use App\Models\Post;
use App\Models\Profile;

/**
 * A post type that owns satellite table rows (poll, quiz, event, job, ...).
 *
 * Handlers are registered against a type in the PostTypeRegistry and are
 * invoked by PostService when a post of that type is created/updated, and by
 * PostResource when the post is serialised.
 */
interface PostTypeHandler
{
    /**
     * Eager-load paths for this type's satellite relations. Kept in
     * PostService::EAGER so a mixed feed never triggers N+1 lookups.
     *
     * @return list<string>
     */
    public function eagerLoad(): array;

    /**
     * Persist the satellite rows for a freshly created post.
     */
    public function createSatellite(Post $post, array $payload): void;

    /**
     * Rebuild the satellite rows for a draft post (published posts are frozen).
     */
    public function updateSatellite(Post $post, array $payload): void;

    /**
     * Satellite fragment merged into the PostResource output. Viewer-specific
     * state is read from transient attributes hydrated by ViewerSatelliteState.
     *
     * @return array<string, mixed>
     */
    public function present(Post $post, ?Profile $viewer): array;
}
