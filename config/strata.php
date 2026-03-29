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

];
