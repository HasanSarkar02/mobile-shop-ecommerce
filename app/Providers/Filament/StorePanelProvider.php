<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Http\Middleware\EnsureTenant;
use App\Http\Middleware\IdentifyTenant;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Auth\MultiFactor\App\AppAuthentication;
class StorePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('store')
            ->path('admin')
            ->login()
            ->colors(['primary' => '#16a34a'])
            ->discoverResources(in: app_path('Filament/Store/Resources'), for: 'App\\Filament\\Store\\Resources')
            ->discoverPages(in: app_path('Filament/Store/Pages'), for: 'App\\Filament\\Store\\Pages')
            ->discoverWidgets(in: app_path('Filament/Store/Widgets'), for: 'App\\Filament\\Store\\Widgets')
            ->widgets([
                        \App\Filament\Store\Widgets\StoreStatsOverview::class,
                        \App\Filament\Store\Widgets\RecentOrdersWidget::class,
                        \App\Filament\Store\Widgets\LowStockWidget::class,
                    ])
            ->middleware([
                        IdentifyTenant::class,
                        EnsureTenant::class,
                        EncryptCookies::class,
                        AddQueuedCookiesToResponse::class,
                        StartSession::class,
                        AuthenticateSession::class,
                        ShareErrorsFromSession::class,
                        VerifyCsrfToken::class,
                        SubstituteBindings::class,
                        DisableBladeIconComponents::class,
                        DispatchServingFilamentEvent::class,
                    ])
                    ->authMiddleware([Authenticate::class])
                    ->multiFactorAuthentication([
                        AppAuthentication::make()->recoverable(),
                    ]);
            }
        }
