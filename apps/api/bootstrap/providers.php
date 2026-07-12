<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\IntegrationSettingsProvider;

return [
    AppServiceProvider::class,
    IntegrationSettingsProvider::class,
    AdminPanelProvider::class,
];
