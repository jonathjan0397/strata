<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     * Available as usePage().props in every Vue component.
     *
     * @return array<string, mixed>
     */
    private function resolveVersion(): string
    {
        $lockPath = storage_path('installed.lock');
        if (file_exists($lockPath)) {
            $lock = json_decode(file_get_contents($lockPath), true);
            if (! empty($lock['version'])) {
                return $lock['version'];
            }
        }
        $composerPath = base_path('composer.json');
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            if (! empty($composer['version'])) {
                return $composer['version'];
            }
        }
        return '1.0-RC1';
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user()?->load('roles')?->makeVisible([
                    'two_factor_enabled',
                    'two_factor_confirmed_at',
                    'two_factor_secret', // needed client-side to know setup is pending
                ]),
            ],
            'flash' => [
                'success' => fn () => $request->hasSession() ? $request->session()->get('success') : null,
                'error'   => fn () => $request->hasSession() ? $request->session()->get('error') : null,
            ],
            'twoFactorWarning' => fn () => $request->user()?->hasAnyRole(['super-admin', 'admin', 'staff'])
                && ! ($request->user()->two_factor_enabled && $request->user()->two_factor_confirmed_at),
            'stripeKey'    => config('services.stripe.key'),
            'siteName'    => fn () => \App\Models\Setting::get('site_title', \App\Models\Setting::get('company_name', config('app.name'))),
            'logoUrl'     => fn () => ($p = \App\Models\Setting::get('logo_path')) ? \Illuminate\Support\Facades\Storage::disk('public')->url($p) : null,
            'portalTheme' => fn () => \App\Models\Setting::get('portal_theme', 'blue'),
            'appVersion'  => fn () => $this->resolveVersion(),
        ]);
    }
}
