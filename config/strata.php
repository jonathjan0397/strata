<?php

return [

    /*
    |--------------------------------------------------------------------------
    | License Server
    |--------------------------------------------------------------------------
    |
    | URL of the private license/telemetry server.
    | Override via STRATA_LICENSE_SERVER_URL in .env if needed.
    |
    | Each installation generates its own unique secret at install time and
    | registers it with the server on first ping — no operator config required.
    |
    */

    'license_server_url' => env('STRATA_LICENSE_SERVER_URL', 'https://ping.stratadevplatform.com'),

    /*
    |--------------------------------------------------------------------------
    | Built-in Updater
    |--------------------------------------------------------------------------
    |
    | Public GitHub repository used for release checks and package downloads.
    | The updater prefers a published release asset, then falls back to the
    | GitHub source ZIP when no asset is attached to the release.
    |
    */

    'update_repo' => env('STRATA_UPDATE_REPO', 'jonathjan0397/strata-billing-support-platform'),

    'update_repo_fallbacks' => [
        'jonathjan0397/strata',
    ],

    'update_channel' => env('STRATA_UPDATE_CHANNEL', 'latest'),

    'update_verify_tls' => env('STRATA_UPDATE_VERIFY_TLS', true),

    /*
    |--------------------------------------------------------------------------
    | Strata Hosting Panel Provisioner
    |--------------------------------------------------------------------------
    |
    | TLS verification for outbound API calls from billing into Strata Hosting
    | Panel instances. Keep this enabled in normal environments and only
    | disable it for local runtimes with a broken CA bundle.
    |
    */

    'panel_api_verify_tls' => env('STRATA_PANEL_API_VERIFY_TLS', true),

];
