<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    /** Redirect to OAuth provider. */
    public function redirect(string $provider): RedirectResponse
    {
        $this->ensureValidProvider($provider);

        return Socialite::driver($provider)->redirect();
    }

    /** Handle OAuth callback — find or create user, then log in. */
    public function callback(string $provider): RedirectResponse
    {
        $this->ensureValidProvider($provider);

        $social = Socialite::driver($provider)->user();

        $user = User::firstOrCreate(
            ['email' => $social->getEmail()],
            [
                'name'              => $social->getName() ?? $social->getNickname() ?? 'User',
                'email_verified_at' => now(),
                'password'          => bcrypt(\Illuminate\Support\Str::random(32)),
            ]
        );

        // Assign client role if no role yet
        if ($user->roles->isEmpty()) {
            $user->assignRole('client');
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    private function ensureValidProvider(string $provider): void
    {
        abort_unless(in_array($provider, ['google', 'microsoft']), 404);
    }
}
