<?php

declare(strict_types=1);

namespace App\Filament\Store\Widgets;

use App\Models\Order;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class ProfitTrendChartWidget extends ChartWidget
{
    protected ?string $heading = 'Revenue vs Profit — Last 30 Days';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $days = collect();
        for ($i = 29; $i >= 0; $i--) {
            $days->push(now()->subDays($i)->toDateString());
        }

        $labels = $days->map(fn (string $d) => Carbon::parse($d)->format('M d'))->all();

        // Tenant-scoped base query; Filament global scope BelongsToTenant handles tenant_id
        $grouped = Order::query()
            ->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered'])
            ->where('placed_at', '>=', now()->subDays(30)->startOfDay())
            ->selectRaw('DATE(placed_at) as d, COALESCE(SUM(subtotal - discount_total),0) as revenue, COALESCE(SUM(cost_total),0) as cogs')
            ->groupBy('d')
            ->get()
            ->keyBy('d');

        $revenue = [];
        $profit = [];
        foreach ($days as $d) {
            $row = $grouped->get($d);
            $rev = $row ? (int) $row->getAttribute('revenue') : 0;
            $cogs = $row ? (int) $row->getAttribute('cogs') : 0;
            $revenue[] = round($rev / 100, 2);
            $profit[] = round(($rev - $cogs) / 100, 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (BDT)',
                    'data' => $revenue,
                    'borderColor' => '#16a34a',
                    'backgroundColor' => 'rgba(22,163,74,0.1)',
                    'fill' => false,
                ],
                [
                    'label' => 'Net Profit (BDT)',
                    'data' => $profit,
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37,99,235,0.1)',
                    'fill' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
