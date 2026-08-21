<?php

declare(strict_types=1);

namespace App\Filament\Store\Widgets;

use App\Models\Order;
use App\Models\StockItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StoreStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $revenue = Order::query()->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered'])->sum('grand_total');
        $todayOrders = Order::query()->whereDate('placed_at', today())->count();
        $pendingOrders = Order::query()->where('status', 'pending')->count();
        $lowStock = StockItem::query()->whereRaw('(quantity - reserved_quantity) <= COALESCE(low_stock_threshold, 5)')->count();

        return [
            Stat::make('Total Revenue', '৳'.number_format($revenue / 100))->color('success'),
            Stat::make('Orders Today', (string) $todayOrders),
            Stat::make('Pending Orders', (string) $pendingOrders)->color($pendingOrders > 0 ? 'warning' : 'success'),
            Stat::make('Low Stock Items', (string) $lowStock)->color($lowStock > 0 ? 'danger' : 'success'),
        ];
    }
}
