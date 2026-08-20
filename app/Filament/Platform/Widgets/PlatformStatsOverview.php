<?php

declare(strict_types=1);

namespace App\Filament\Platform\Widgets;

use App\Filament\Platform\Support\PlatformMoney;
use App\Services\PlatformDashboardService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $kpis = app(PlatformDashboardService::class)->kpis();

        return [
            Stat::make('Total Tenants', (string) $kpis['total_tenants']),
            Stat::make('Active Tenants', (string) $kpis['active_tenants'])->color('success'),
            Stat::make('Trial Tenants', (string) $kpis['trial_tenants'])->color('warning'),
            Stat::make('Expiring Subscriptions', (string) $kpis['expiring_subscriptions'])
                ->description('next 7 days')
                ->color('warning'),
            Stat::make('Pending Plan Change Requests', (string) $kpis['pending_plan_change_requests'])
                ->color('warning'),
            Stat::make('Pending Subscription Payments', (string) $kpis['pending_subscription_payments'])
                ->color('warning'),
            Stat::make('Outstanding Subscription Charges', PlatformMoney::format($kpis['outstanding_amount']))
                ->description((string) $kpis['outstanding_charges'].' charges outstanding')
                ->color('danger'),
            Stat::make('Active Custom Domains', (string) $kpis['active_domains'])->color('success'),
            Stat::make('DNS Pending', (string) $kpis['dns_pending'])->color('warning'),
            Stat::make('DNS Failed', (string) $kpis['dns_failed'])->color('danger'),
        ];
    }
}
