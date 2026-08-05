<?php

use App\Models\Follow;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create a user with a personal profile.
 */
function userWithProfile(array $profileAttributes = [], array $userAttributes = []): User
{
    $user = User::factory()->create($userAttributes);

    Profile::factory()->for($user)->create($profileAttributes);

    return $user->fresh();
}

/**
 * Create an admin user with a personal profile. is_admin is guarded, so it is
 * force-filled after creation.
 */
function adminWithProfile(array $profileAttributes = [], array $userAttributes = []): User
{
    $user = userWithProfile($profileAttributes, $userAttributes);
    $user->forceFill(['is_admin' => true])->save();

    return $user->fresh();
}

/**
 * Create a super-admin user with a personal profile (implies admin).
 */
function superAdminWithProfile(array $profileAttributes = [], array $userAttributes = []): User
{
    $user = userWithProfile($profileAttributes, $userAttributes);
    $user->forceFill(['is_admin' => true, 'is_super_admin' => true])->save();

    return $user->fresh();
}

/**
 * Create an accepted follow between two profiles.
 */
function acceptedFollow(Profile $follower, Profile $followed): Follow
{
    return Follow::factory()->create([
        'follower_profile_id' => $follower->id,
        'followed_profile_id' => $followed->id,
    ]);
}
