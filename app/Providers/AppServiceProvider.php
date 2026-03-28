<?php

namespace App\Providers;

use App\Listeners\LogSentEmail;
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

        if (! file_exists(storage_path('installed.lock'))) {
            // Pre-install: switch cache to array so nothing tries to hit the
            // database before credentials have been configured.
            config(['cache.default' => 'array']);

            // Pre-install: APP_URL is still the default 'http://localhost'.
            // Detect the real base URL from the incoming request so that route()
            // and Ziggy generate correct absolute URLs regardless of which
            // subdirectory the app is installed in.
            if (! app()->runningInConsole()) {
                $detected = request()->getSchemeAndHttpHost() . request()->getBaseUrl();
                URL::forceRootUrl($detected);
            }
        }
    }
}
