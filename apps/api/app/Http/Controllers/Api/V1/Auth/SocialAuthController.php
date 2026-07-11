<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class SocialAuthController extends Controller
{
    private const PROVIDERS = ['google', 'facebook', 'twitter'];

    public function redirect(string $provider): Response
    {
        $this->ensureEnabled($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider, AuthService $auth): RedirectResponse
    {
        $this->ensureEnabled($provider);

        $socialUser = Socialite::driver($provider)->user();

        $user = $auth->findOrCreateSocialUser($provider, $socialUser);

        if ($user->isBanned()) {
            abort(403, 'Your account has been banned.');
        }

        Auth::guard('web')->login($user, remember: true);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return redirect()->away(config('app.frontend_url'));
    }

    private function ensureEnabled(string $provider): void
    {
        abort_unless(
            in_array($provider, self::PROVIDERS, true) && config("services.{$provider}.client_id"),
            404
        );
    }
}
