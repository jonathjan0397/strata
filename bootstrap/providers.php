<?php

use App\Providers\AppServiceProvider;
use App\Providers\IntegrationSettingsServiceProvider;
use App\Providers\MailSettingsServiceProvider;

return [
    AppServiceProvider::class,
    MailSettingsServiceProvider::class,
    IntegrationSettingsServiceProvider::class,
];
