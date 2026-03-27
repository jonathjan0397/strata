<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorChallengeController extends Controller
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('two_factor_login_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'digits:6']]);

        $userId = $request->session()->get('two_factor_login_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user   = User::findOrFail($userId);
        $secret = decrypt($user->two_factor_secret);

        $valid = $this->google2fa->verifyKey($secret, $request->code);

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => ['The code is invalid.'],
            ]);
        }

        $request->session()->forget('two_factor_login_id');
        $request->session()->regenerate();

        Auth::loginUsingId($userId, $request->session()->get('two_factor_remember', false));

        $request->session()->forget('two_factor_remember');

        AuditLogger::log('auth.login_2fa', null, [], $userId);

        return redirect()->intended(route('dashboard'));
    }
}
