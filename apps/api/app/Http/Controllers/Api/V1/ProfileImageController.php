<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use App\Services\MediaService;
use Illuminate\Http\Request;

/**
 * Upload / remove a profile's avatar (all profiles) and cover banner
 * (business profiles only). Images are cropped client-side, then re-encoded
 * server-side to fixed WebP dimensions (avatar 512×512, cover 1500×500).
 */
class ProfileImageController extends Controller
{
    public function __construct(private MediaService $media) {}

    public function storeAvatar(Request $request, Profile $profile): ProfileResource
    {
        $this->authorizeOwner($request, $profile);

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$this->maxKb()],
        ]);

        $this->media->storeAvatar($profile, $request->file('image'));

        return new ProfileResource($profile->fresh()->load('businessCategory'));
    }

    public function destroyAvatar(Request $request, Profile $profile): ProfileResource
    {
        $this->authorizeOwner($request, $profile);

        $this->media->removeProfileImage($profile, 'avatar_path');

        return new ProfileResource($profile->fresh()->load('businessCategory'));
    }

    public function storeCover(Request $request, Profile $profile): ProfileResource
    {
        $this->authorizeOwner($request, $profile);

        // Cover banners are a business-profile feature.
        abort_unless($profile->isBusiness(), 422, 'Only business profiles have a cover banner.');

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.$this->maxKb()],
        ]);

        $this->media->storeCover($profile, $request->file('image'));

        return new ProfileResource($profile->fresh()->load('businessCategory'));
    }

    public function destroyCover(Request $request, Profile $profile): ProfileResource
    {
        $this->authorizeOwner($request, $profile);

        $this->media->removeProfileImage($profile, 'cover_path');

        return new ProfileResource($profile->fresh()->load('businessCategory'));
    }

    private function authorizeOwner(Request $request, Profile $profile): void
    {
        abort_unless($profile->user_id === $request->user()->id, 403);
    }

    private function maxKb(): int
    {
        return (int) config('media.max_upload_kb', 10240);
    }
}
