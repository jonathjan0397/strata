<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        $destination = $user->hasAnyRole(['super-admin', 'admin', 'staff'])
            ? route('admin.dashboard')
            : route('client.dashboard');

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended($destination.'?verified=1');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended($destination.'?verified=1');
    }
}
