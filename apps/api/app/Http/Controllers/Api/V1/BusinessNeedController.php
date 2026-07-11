<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessNeedRequest;
use App\Http\Requests\UpdateBusinessNeedRequest;
use App\Http\Resources\BusinessNeedResource;
use App\Models\BusinessNeed;
use App\Models\Profile;
use App\Services\Business\BusinessNeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BusinessNeedController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $profile = $this->businessProfile($request);

        return BusinessNeedResource::collection(
            $profile->businessNeeds()
                ->with('businessCategory')
                ->latest('id')
                ->get()
        );
    }

    public function store(StoreBusinessNeedRequest $request, BusinessNeedService $needs): JsonResponse
    {
        $profile = $this->businessProfile($request);

        $need = $needs->create($profile, $request->validated());

        return (new BusinessNeedResource($need))->response()->setStatusCode(201);
    }

    public function update(UpdateBusinessNeedRequest $request, BusinessNeed $businessNeed, BusinessNeedService $needs): BusinessNeedResource
    {
        $profile = $this->businessProfile($request);

        abort_unless($businessNeed->profile_id === $profile->id, 403);

        return new BusinessNeedResource($needs->update($businessNeed, $request->validated()));
    }

    public function destroy(Request $request, BusinessNeed $businessNeed, BusinessNeedService $needs): Response
    {
        $profile = $this->businessProfile($request);

        abort_unless($businessNeed->profile_id === $profile->id, 403);

        $needs->delete($businessNeed);

        return response()->noContent();
    }

    /**
     * The active profile, which must be a business profile.
     */
    private function businessProfile(Request $request): Profile
    {
        $profile = $request->attributes->get('activeProfile');

        abort_unless($profile instanceof Profile, 422, 'No active profile.');
        abort_unless($profile->isBusiness(), 403, 'Only business profiles have needs.');

        return $profile;
    }
}
