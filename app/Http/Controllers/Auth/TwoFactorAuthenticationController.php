<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthenticationController extends Controller
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    /** Generate a new secret and store it (unconfirmed). */
    public function store(Request $request): RedirectResponse
    {
        $secret = $this->google2fa->generateSecretKey();

        $request->user()->forceFill([
            'two_factor_secret' => encrypt($secret),
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('status', '2fa-secret-generated');
    }

    /** Return the QR code SVG for the current user's unconfirmed secret. */
    public function qrCode(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = decrypt($user->two_factor_secret);

        $url = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $renderer = new ImageRenderer(new RendererStyle(192), new SvgImageBackEnd);
        $svg = (new Writer($renderer))->writeString($url);

        return response()->json([
            'svg' => $svg,
            'secret' => $secret,
        ]);
    }

    /** Confirm the secret by verifying the first OTP. */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'digits:6']]);

        $user = $request->user();
        $secret = decrypt($user->two_factor_secret);

        $valid = $this->google2fa->verifyKey($secret, $request->code);

        if (! $valid) {
            return back()->withErrors(['code' => 'The code is invalid. Please try again.']);
        }

        $user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return back()->with('status', '2fa-enabled');
    }

    /** Disable 2FA and clear the secret. */
    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('status', '2fa-disabled');
    }
}
