<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Registrar Driver
    |--------------------------------------------------------------------------
    | Options: namecheap, enom, opensrs, hexonet
    */
    'default' => env('REGISTRAR_DRIVER', 'namecheap'),

    'namecheap' => [
        'sandbox'   => env('NAMECHEAP_SANDBOX', true),
        'api_user'  => env('NAMECHEAP_API_USER'),
        'api_key'   => env('NAMECHEAP_API_KEY'),
        'client_ip' => env('NAMECHEAP_CLIENT_IP', '127.0.0.1'),
    ],

    'enom' => [
        'sandbox' => env('ENOM_SANDBOX', true),
        'uid'     => env('ENOM_UID'),
        'pw'      => env('ENOM_PW'),
    ],

    'opensrs' => [
        'sandbox'            => env('OPENSRS_SANDBOX', true),
        'api_key'            => env('OPENSRS_API_KEY'),
        'reseller_username'  => env('OPENSRS_RESELLER_USERNAME'),
    ],

    'hexonet' => [
        'sandbox'  => env('HEXONET_SANDBOX', true),
        'login'    => env('HEXONET_LOGIN'),
        'password' => env('HEXONET_PASSWORD'),
    ],

];
