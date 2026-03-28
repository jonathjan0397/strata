<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    App\Providers\MailSettingsServiceProvider::class,
    App\Providers\IntegrationSettingsServiceProvider::class,
];
