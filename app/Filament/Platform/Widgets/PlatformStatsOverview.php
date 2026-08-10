<?php

declare(strict_types=1);

namespace App\Filament\Platform\Widgets;

use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Total Tenants', (string) Tenant::query()->count()),
            Stat::make('Active Tenants', (string) Tenant::query()->where('status', 'active')->count())->color('success'),
            Stat::make('Trial Tenants', (string) Tenant::query()->where('status', 'trial')->count())->color('warning'),
            Stat::make('New This Month', (string) Tenant::query()->whereMonth('created_at', now()->month)->count()),
        ];
    }
}