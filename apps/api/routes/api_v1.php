<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Auth\SocialAuthController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\FollowController;
use App\Http\Controllers\Api\V1\FollowRequestController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MyProfileController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes (prefix: /api/v1)
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('register', RegisterController::class)->middleware('throttle:auth');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:auth');
    Route::post('logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('token', TokenController::class)->middleware('throttle:auth');

    Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->middleware('throttle:auth');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:auth');

    // Socialite web flow needs sessions, hence the web middleware group.
    Route::middleware('web')->group(function () {
        Route::get('{provider}/redirect', [SocialAuthController::class, 'redirect'])
            ->whereIn('provider', ['google', 'facebook', 'twitter']);
        Route::get('{provider}/callback', [SocialAuthController::class, 'callback'])
            ->whereIn('provider', ['google', 'facebook', 'twitter']);
    });
});

// Public profile routes (viewer resolved when authenticated).
Route::middleware('profile.active')->group(function () {
    Route::get('profiles/{handle}', [ProfileController::class, 'show']);
    Route::get('profiles/{handle}/followers', [ProfileController::class, 'followers']);
    Route::get('profiles/{handle}/following', [ProfileController::class, 'following']);
});

Route::middleware(['auth:sanctum', 'not_banned', 'profile.active'])->group(function () {
    Route::get('me', [MeController::class, 'show']);
    Route::patch('me', [MeController::class, 'update']);

    Route::get('me/profiles', [MyProfileController::class, 'index']);
    Route::post('me/profiles', [MyProfileController::class, 'store']);
    Route::patch('me/profiles/{profile}', [MyProfileController::class, 'update']);
    Route::delete('me/profiles/{profile}', [MyProfileController::class, 'destroy']);

    Route::get('me/follow-requests', [FollowRequestController::class, 'index']);
    Route::post('me/follow-requests/{follow}/accept', [FollowRequestController::class, 'accept']);
    Route::post('me/follow-requests/{follow}/decline', [FollowRequestController::class, 'decline']);

    Route::post('profiles/{handle}/follow', [FollowController::class, 'store']);
    Route::delete('profiles/{handle}/follow', [FollowController::class, 'destroy']);
});
