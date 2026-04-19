<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Services\StrataLicense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
                'warning' => fn () => $request->hasSession() ? $request->session()->get('warning') : null,
                'error' => fn () => $request->hasSession() ? $request->session()->get('error') : null,
            ],
            'twoFactorWarning' => fn () => $request->user()?->hasAnyRole(['super-admin', 'admin', 'staff'])
                && ! ($request->user()->two_factor_enabled && $request->user()->two_factor_confirmed_at),
            'stripeKey' => config('services.stripe.key'),
            'oauthProviders' => fn () => [
                'google'    => (bool) Setting::get('integration_google_client_id'),
                'microsoft' => (bool) Setting::get('integration_microsoft_client_id'),
            ],
            'siteName' => fn () => Setting::get('site_title', Setting::get('company_name', config('app.name'))),
            'logoUrl' => fn () => ($p = Setting::get('logo_path')) ? Storage::disk('public')->url($p) : null,
            'portalTheme' => fn () => Setting::get('portal_theme', 'blue'),
            'appVersion'    => fn () => $this->resolveVersion(),
            'license'       => fn () => $this->resolveLicense(),
            'portalBranding' => fn () => [
                'primaryColor' => Setting::get('brand_primary_color'),
                'accentColor'  => Setting::get('brand_accent_color'),
                'heroBadge'    => Setting::get('portal_hero_badge'),
                'heroTitle'    => Setting::get('portal_hero_title'),
                'footerLinks'   => json_decode(Setting::get('portal_footer_links', '[]'), true) ?: [],
                'featureCards'  => json_decode(Setting::get('portal_feature_cards', 'null'), true),
                'statItems'     => json_decode(Setting::get('portal_stat_items', 'null'), true),
                'customCss'     => Setting::get('portal_custom_css'),
                'faviconUrl'    => ($p = Setting::get('favicon_path')) ? Storage::disk('public')->url($p) : null,
            ],
        ]);
    }

    private function resolveLicense(): array
    {
        if (! StrataLicense::isManaged()) {
            return [
                'managed'         => false,
                'active'          => true,
                'status'          => 'active',
                'features'        => [],
                'messages'        => [],
                'first_run'       => false,
                'stale'           => false,
                'synced_at'       => null,
            ];
        }

        $payload = StrataLicense::ensureCurrent();
        $user = request()->user();
        $showAdminMessages = $user?->hasAnyRole(['super-admin', 'admin']) ?? false;

        return [
            'managed'         => true,
            'active'          => ($payload['status'] ?? 'active') === 'active',
            'status'          => $payload['status'] ?? 'active',
            'features'        => StrataLicense::features(),
            'messages'        => $showAdminMessages ? StrataLicense::messages() : [],
            'first_run'       => $showAdminMessages ? StrataLicense::isFreshInstall() : false,
            'stale'           => $showAdminMessages ? StrataLicense::isStale() : false,
            'synced_at'       => $payload['synced_at'] ?? null,
        ];
    }
}
