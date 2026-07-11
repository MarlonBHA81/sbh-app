<?php

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
