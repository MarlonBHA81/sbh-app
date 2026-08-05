<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Models\Profile;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function store(StoreMediaRequest $request, MediaService $service): JsonResponse
    {
        /** @var Profile|null $profile */
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile, 403, 'An active profile is required to upload media.');

        $media = $service->storeImage($profile, $request->file('file'));

        return (new MediaResource($media))->response()->setStatusCode(201);
    }

    /**
     * Processing-status polling for a media item (owner only).
     */
    public function show(Request $request, Media $media): MediaResource
    {
        /** @var Profile|null $profile */
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile && $media->profile_id === $profile->id, 403);

        return new MediaResource($media);
    }
}
