<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title inertia>{{ \App\Models\Setting::get('site_title', config('app.name')) }}</title>
    <script>window.__SITE_NAME__ = @json(\App\Models\Setting::get('site_title', config('app.name')));</script>

    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
    @if(config('services.stripe.key'))
        <script src="https://js.stripe.com/v3/" defer></script>
    @endif
</head>
<body class="h-full font-sans antialiased">
    @inertia
</body>
</html>
