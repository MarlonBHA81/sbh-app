<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\UserResource;
use App\Models\Setting;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, AuthService $auth): JsonResponse
    {
        if (! Setting::get('registration_open', true)) {
            return response()->json([
                'message' => __('Registration is currently closed.'),
            ], 403);
        }

        $user = $auth->register($request->validated());

        Auth::guard('web')->login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'user' => new UserResource($user),
            'profile' => new ProfileResource($user->personalProfile),
        ], 201);
    }
}
