<?php

namespace App\Services\Account;

use App\Models\Media;
use App\Models\Post;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Assembles a data-subject export and performs a full account erasure.
 * Kept deliberately simple and self-contained so the compliance behaviour
 * is auditable in one place.
 */
class AccountDataService
{
    /**
     * A portable copy of everything personal we hold about the user.
     *
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $user->loadMissing('profiles');

        return [
            'exported_at' => now()->toISOString(),
            'account' => [
                'email' => $user->email,
                'name' => $user->name,
                'locale' => $user->locale,
                'timezone' => $user->timezone,
                'created_at' => $user->created_at?->toISOString(),
                'settings' => $user->settings,
            ],
            'profiles' => $user->profiles->map(fn (Profile $p) => [
                'handle' => $p->handle,
                'name' => $p->name,
                'kind' => $p->kind,
                'bio' => $p->bio,
                'website' => $p->website,
                'social_links' => $p->social_links,
                'location' => $p->location,
                'followers_count' => $p->followers_count,
                'following_count' => $p->following_count,
                'posts_count' => $p->posts_count,
                'xp_total' => $p->xp_total,
                'created_at' => $p->created_at?->toISOString(),
            ])->all(),
            'posts' => Post::query()
                ->whereIn('profile_id', $user->profiles->pluck('id'))
                ->withTrashed()
                ->get(['ulid', 'type', 'body', 'visibility', 'status', 'created_at'])
                ->map(fn (Post $post) => [
                    'ulid' => $post->ulid,
                    'type' => $post->type,
                    'body' => $post->body,
                    'visibility' => $post->visibility,
                    'status' => $post->status,
                    'created_at' => $post->created_at?->toISOString(),
                ])->all(),
        ];
    }

    /**
     * Permanently delete the account: media files, then all owned rows via
     * FK cascades (profiles cascade from user; posts/media/etc. cascade from
     * profiles), then the user record itself.
     */
    public function deleteAccount(User $user): void
    {
        $profileIds = $user->profiles()->pluck('id');

        // Remove media files from disk first — DB cascade deletes the rows
        // but not the underlying storage objects.
        Media::query()
            ->whereHasMorph('mediable', [Post::class], function ($query) use ($profileIds) {
                $query->whereIn('profile_id', $profileIds);
            })
            ->get()
            ->each(function (Media $media) {
                foreach (array_filter([$media->path, $media->thumb_path]) as $path) {
                    Storage::disk($media->disk)->delete($path);
                }
            });

        foreach ($user->profiles as $profile) {
            foreach (array_filter([$profile->avatar_path, $profile->cover_path]) as $path) {
                Storage::disk('public')->delete($path);
            }
        }

        DB::transaction(function () use ($user) {
            // Tokens and profiles cascade on user delete via FK constraints;
            // deleting the user is the single source of erasure.
            $user->tokens()->delete();
            $user->forceDelete();
        });
    }
}
