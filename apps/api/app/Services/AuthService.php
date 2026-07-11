<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class AuthService
{
    public function __construct(
        private readonly ProfileService $profiles,
    ) {}

    /**
     * Register a new user with a personal profile.
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $this->profiles->createPersonalProfile($user, $data['handle'] ?? null);

            return $user;
        });
    }

    /**
     * Find or create a user from a Socialite callback.
     */
    public function findOrCreateSocialUser(string $provider, SocialiteUser $socialUser): User
    {
        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', (string) $socialUser->getId())
            ->first();

        if ($account) {
            return $account->user;
        }

        return DB::transaction(function () use ($provider, $socialUser) {
            $email = $socialUser->getEmail();

            $user = $email ? User::query()->where('email', $email)->first() : null;

            if (! $user) {
                $user = new User([
                    'name' => $socialUser->getName() ?: ($socialUser->getNickname() ?: 'User'),
                    'email' => $email,
                ]);
                $user->email_verified_at = now();
                $user->save();

                $this->profiles->createPersonalProfile($user, $socialUser->getNickname());
            } elseif ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_id' => (string) $socialUser->getId(),
            ]);

            return $user;
        });
    }
}
