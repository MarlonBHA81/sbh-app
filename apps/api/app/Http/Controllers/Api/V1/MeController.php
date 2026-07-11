<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMeRequest;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('profiles.badges');

        $active = $request->attributes->get('activeProfile');

        return response()->json([
            'user' => new UserResource($user),
            'profiles' => ProfileResource::collection($user->profiles),
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
