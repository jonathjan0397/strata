<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = Auth::user();

        // If 2FA is confirmed, suspend the session and send to challenge page.
        if ($user->two_factor_enabled && $user->two_factor_confirmed_at) {
            $remember = $request->boolean('remember');
            Auth::logout();

            $request->session()->put('two_factor_login_id', $user->id);
            $request->session()->put('two_factor_remember', $remember);

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        AuditLogger::log('auth.login', null, [], $user->id);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $userId = Auth::id();
        AuditLogger::log('auth.logout', null, [], $userId);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
