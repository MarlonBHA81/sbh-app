<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMeRequest;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\UserResource;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('profiles.badges', 'memberProfiles.badges');

        $active = $request->attributes->get('activeProfile');

        // Owned profiles (personal + business) plus business profiles shared
        // with the user (Roles P4), deduped, each annotated with the viewer's role.
        $profiles = $user->profiles
            ->concat($user->memberProfiles)
            ->unique('id')
            ->values()
            ->each(fn (Profile $profile) => $profile->my_role = $profile->memberRole($user));

        if ($active) {
            $active->my_role = $active->memberRole($user);
        }

        return response()->json([
            'user' => new UserResource($user),
            'profiles' => ProfileResource::collection($profiles),
            'active_profile' => $active ? new ProfileResource($active) : null,
        ]);
    }

    public function update(UpdateMeRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated())->save();

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }
}
