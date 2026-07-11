<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessProfileRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class MyProfileController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ProfileResource::collection(
            $request->user()->profiles()->with('badges')->get()
        );
    }

    public function store(StoreBusinessProfileRequest $request, ProfileService $profiles): JsonResponse
    {
        $profile = $profiles->createBusinessProfile($request->user(), $request->validated());

        return (new ProfileResource($profile))->response()->setStatusCode(201);
    }

    public function update(UpdateProfileRequest $request, Profile $profile, ProfileService $profiles): ProfileResource
    {
        abort_unless($profile->user_id === $request->user()->id, 403);

        return new ProfileResource($profiles->updateProfile($profile, $request->validated()));
    }

    public function destroy(Request $request, Profile $profile, ProfileService $profiles): Response
    {
        abort_unless($profile->user_id === $request->user()->id, 403);

        $profiles->deleteBusinessProfile($profile);

        return response()->noContent();
    }
}
