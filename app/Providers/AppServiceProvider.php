<?php

namespace App\Providers;

use App\Listeners\LogSentEmail;
use App\Models\Setting;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(MessageSent::class, LogSentEmail::class);

        // Overlay Socialite credentials from the settings table so admins can
        // configure OAuth without touching .env or server config files.
        if (! app()->runningInConsole() && file_exists(storage_path('installed.lock'))) {
            try {
                if ($id = Setting::get('integration_google_client_id')) {
                    config([
                        'services.google.client_id'     => $id,
                        'services.google.client_secret' => Setting::get('integration_google_client_secret'),
                    ]);
                }
                if ($id = Setting::get('integration_microsoft_client_id')) {
                    config([
                        'services.microsoft.client_id'     => $id,
                        'services.microsoft.client_secret' => Setting::get('integration_microsoft_client_secret'),
                        'services.microsoft.tenant'        => Setting::get('integration_microsoft_tenant', 'common'),
                    ]);
                }
            } catch (\Throwable) {
                // DB may not be ready yet — skip silently
            }
        }

        if (! file_exists(storage_path('installed.lock'))) {
            // Pre-install: switch cache to array so nothing tries to hit the
            // database before credentials have been configured.
            config(['cache.default' => 'array']);

            // Pre-install: APP_URL is still the default 'http://localhost'.
            // Detect the real base URL from the incoming request so that route()
            // and Ziggy generate correct absolute URLs regardless of which
            // subdirectory the app is installed in.
            if (! app()->runningInConsole()) {
                $detected = request()->getSchemeAndHttpHost().request()->getBaseUrl();
                URL::forceRootUrl($detected);
            }
        }
    }
}
