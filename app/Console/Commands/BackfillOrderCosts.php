<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Command;

class BackfillOrderCosts extends Command
{
    protected $signature = 'orders:backfill-costs {--tenant= : Specific tenant ID}';

    protected $description = 'Best-effort backfill of order_items.unit_cost_price/line_cost and orders.cost_total from current variant cost_price (additive, idempotent)';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $tenants = $tenantId ? Tenant::query()->whereKey($tenantId)->get() : Tenant::query()->get();

        if ($tenants->isEmpty()) {
            $this->error('No tenants found.');

            return self::FAILURE;
        }

        $totalOrders = 0;
        $totalItems = 0;

        foreach ($tenants as $tenant) {
            app(Tenancy::class)->set($tenant);

            Order::query()->with(['items.variant'])->chunkById(100, function ($orders) use (&$totalOrders, &$totalItems) {
                foreach ($orders as $order) {
                    $changed = false;
                    $costTotal = 0;

                    foreach ($order->items as $item) {
                        $unitCost = $item->unit_cost_price;
                        $lineCost = $item->line_cost;

                        if ($unitCost === null) {
                            $unitCost = $item->variant ? (int) ($item->variant->cost_price ?? 0) : 0;
                            $lineCost = $this->lineTotal($unitCost, $item->quantity);
                            $item->updateQuietly([
                                'unit_cost_price' => $unitCost,
                                'line_cost' => $lineCost,
                            ]);
                            $totalItems++;
                            $changed = true;
                        } elseif ($lineCost === null) {
                            $lineCost = $this->lineTotal((int) $unitCost, $item->quantity);
                            $item->updateQuietly(['line_cost' => $lineCost]);
                            $totalItems++;
                            $changed = true;
                        }

                        $costTotal += (int) ($item->fresh()->line_cost ?? $lineCost);
                    }

                    if ((int) $order->cost_total !== $costTotal) {
                        $order->updateQuietly(['cost_total' => $costTotal]);
                        $totalOrders++;
                        $changed = true;
                    }

                    // If nothing changed but cost_total still 0 and items exist, ensure counted?
                    if ($changed) {
                        // already counted via cost_total diff
                    }
                }
            });

            $this->info("Tenant {$tenant->name} ({$tenant->id}): backfilled");
        }

        app(Tenancy::class)->set(null);

        $this->info("Done. Orders updated: {$totalOrders}, items backfilled: {$totalItems}");

        return self::SUCCESS;
    }

    private function lineTotal(int $unitPrice, int|float|string $quantity): int
    {
        $qty = number_format((float) $quantity, 3, '.', '');
        $raw = bcmul((string) $unitPrice, $qty, 3);

        return (int) bcadd($raw, '0.5', 0);
    }
}
