<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\CipcVerifier;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessProfileRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use App\Services\Business\CipcResult;
use App\Services\Gamification\GamificationService;
use App\Services\ProfileService;
use App\Services\Webhooks\WebhookDispatcher;
use App\Support\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

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
        $data = $request->validated();

        // Hard gate: a business profile may only be created once CIPC confirms
        // its registration number. Verify BEFORE creating anything.
        $result = $this->verifyCipc($data['registration_number']);

        $profile = $profiles->createBusinessProfile($request->user(), $data);

        $profile->forceFill([
            'registration_number' => $data['registration_number'],
            'cipc_registered_name' => $result->registeredName,
            'cipc_verified_at' => now(),
        ])->save();

        app(GamificationService::class)->award(
            $profile,
            GamificationService::BUSINESS_CIPC_VERIFIED,
            $profile,
        );

        Activity::log('profile.cipc_verified', $profile, [
            'registration_number' => $data['registration_number'],
            'registered_name' => $result->registeredName,
        ]);

        return (new ProfileResource($profile))->response()->setStatusCode(201);
    }

    /**
     * Look up the registration number against CIPC, translating anything other
     * than a confirmed hit into a validation error on registration_number.
     */
    private function verifyCipc(string $registrationNumber): CipcResult
    {
        $result = app(CipcVerifier::class)->lookup($registrationNumber);

        if ($result->isVerified()) {
            return $result;
        }

        if ($result->isUnavailable()) {
            throw ValidationException::withMessages([
                'registration_number' => ['Business verification via CIPC is currently unavailable. Please try again later.'],
            ]);
        }

        throw ValidationException::withMessages([
            'registration_number' => ["That registration number wasn't found on CIPC."],
        ]);
    }

    public function update(UpdateProfileRequest $request, Profile $profile, ProfileService $profiles, WebhookDispatcher $webhooks): ProfileResource
    {
        abort_unless($profile->user_id === $request->user()->id, 403);

        $updated = $profiles->updateProfile($profile, $request->validated());

        // Sync the updated contact to any configured CRM (Brevo or any).
        $webhooks->contact(WebhookDispatcher::CONTACT_UPDATED, $updated);

        return new ProfileResource($updated->load('businessCategory'));
    }

    public function destroy(Request $request, Profile $profile, ProfileService $profiles): Response
    {
        abort_unless($profile->user_id === $request->user()->id, 403);

        $profiles->deleteBusinessProfile($profile);

        return response()->noContent();
    }
}
