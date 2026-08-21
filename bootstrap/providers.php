<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\PlatformPanelProvider;
use App\Providers\Filament\StorePanelProvider;

return [
    AppServiceProvider::class,
    PlatformPanelProvider::class,
    StorePanelProvider::class,
];
