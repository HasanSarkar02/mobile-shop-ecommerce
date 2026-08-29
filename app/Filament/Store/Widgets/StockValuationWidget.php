<?php

declare(strict_types=1);

namespace App\Filament\Store\Widgets;

use App\Services\Inventory\StockValuationService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StockValuationWidget extends BaseWidget
{
    protected ?string $heading = 'Inventory Valuation';

    protected function getStats(): array
    {
        $service = app(StockValuationService::class);

        $totalValue = $service->totalOnHandValue();
        $unvalued = $service->unvaluedCount();

        return [
            Stat::make('Total Inventory Value', money($totalValue))
                ->description('On-hand quantity × unit cost')
                ->color('success')
                ->icon('heroicon-o-currency-dollar'),
            Stat::make('Unvalued Items', (string) $unvalued)
                ->description($unvalued > 0 ? 'Variants without cost_price' : 'All items valued')
                ->color($unvalued > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }
}
