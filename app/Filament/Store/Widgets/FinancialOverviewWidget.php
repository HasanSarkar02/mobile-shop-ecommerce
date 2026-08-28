<?php

declare(strict_types=1);

namespace App\Filament\Store\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancialOverviewWidget extends BaseWidget
{
    protected ?string $heading = 'Financial Overview';

    protected function getStats(): array
    {
        // Revenue is net sales (subtotal - discount), shipping excluded per Q1
        $base = Order::query()->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered']);

        $revenueSubtotal = (int) $base->clone()->sum('subtotal');
        $discountTotal = (int) $base->clone()->sum('discount_total');
        $revenue = $revenueSubtotal - $discountTotal;
        $cogs = (int) $base->clone()->sum('cost_total');

        $netProfit = $revenue - $cogs;
        $margin = $revenue > 0 ? round(($netProfit / $revenue) * 100, 1) : null;

        return [
            Stat::make('Total Revenue', money($revenue))
                ->description('Net sales (subtotal − discount)')
                ->color('success'),
            Stat::make('Total COGS', money($cogs))
                ->description('Snapshot cost at order time')
                ->color('gray'),
            Stat::make('Net Profit', money($netProfit))
                ->description($netProfit >= 0 ? 'Revenue − COGS' : 'Loss')
                ->color($netProfit >= 0 ? 'success' : 'danger'),
            Stat::make('Margin %', $margin !== null ? $margin.'%' : '—')
                ->description($margin !== null ? 'Profit / Revenue' : 'No revenue yet')
                ->color($margin !== null && $margin >= 20 ? 'success' : ($margin !== null && $margin >= 0 ? 'warning' : 'danger')),
        ];
    }
}
