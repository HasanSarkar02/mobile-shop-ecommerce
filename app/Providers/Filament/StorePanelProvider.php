<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Store\Widgets\LowStockWidget;
use App\Filament\Store\Widgets\RecentOrdersWidget;
use App\Filament\Store\Widgets\StoreStatsOverview;
use App\Http\Middleware\EnsureTenant;
use App\Http\Middleware\ResolveSupportSession;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class StorePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('store')
            ->path('admin')
            ->sidebarCollapsibleOnDesktop()
            ->login()
            ->profile()
            ->colors(['primary' => '#16a34a'])
            ->discoverResources(in: app_path('Filament/Store/Resources'), for: 'App\\Filament\\Store\\Resources')
            ->discoverPages(in: app_path('Filament/Store/Pages'), for: 'App\\Filament\\Store\\Pages')
            ->discoverWidgets(in: app_path('Filament/Store/Widgets'), for: 'App\\Filament\\Store\\Widgets')
            ->widgets([
                StoreStatsOverview::class,
                RecentOrdersWidget::class,
                LowStockWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ResolveSupportSession::class,
                EnsureTenant::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => view('filament.store.support-banner')->render(),
            )
            ->renderHook(
                PanelsRenderHook::SIMPLE_LAYOUT_START,
                fn (): string => view('filament.store.support-banner')->render(),
            )
            ->authMiddleware([Authenticate::class])
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ]);
    }
}
