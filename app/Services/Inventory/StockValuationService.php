<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\ProductVariant;
use App\Models\StockItem;
use Illuminate\Support\Facades\DB;

class StockValuationService
{
    /**
     * Value on hand for a single StockItem row (quantity * cost_price).
     * Returns null when variant has no cost_price (unvalued).
     */
    public function valueOnHand(StockItem $item): ?int
    {
        $variant = $item->variant;

        if (! $variant instanceof ProductVariant) {
            return null;
        }

        $cost = $variant->cost_price;

        if ($cost === null) {
            return null;
        }

        return $this->multiplyQuantityCost((string) $item->quantity, (int) $cost);
    }

    /**
     * Total inventory value across all StockItems for current tenant.
     * Uses DB-level aggregation for performance (quantity decimal * int cost).
     */
    public function totalOnHandValue(): int
    {
        $total = StockItem::query()
            ->join('product_variants', function ($join): void {
                $join->on('product_variants.id', '=', 'stock_items.product_variant_id')
                    ->whereNotNull('product_variants.cost_price');
            })
            ->selectRaw('COALESCE(SUM(stock_items.quantity * product_variants.cost_price), 0) as total')
            ->value('total');

        return (int) round((float) ($total ?? 0));
    }

    /**
     * Count of stock rows where variant has no cost_price (or orphan).
     */
    public function unvaluedCount(): int
    {
        // StockItem::variant withTrashed is used in resource, but valuation should count only existing variants with null cost.
        // Use join approach to stay consistent with totalOnHandValue.
        return StockItem::query()
            ->leftJoin('product_variants', 'product_variants.id', '=', 'stock_items.product_variant_id')
            ->where(function ($q): void {
                $q->whereNull('product_variants.cost_price')
                    ->orWhereNull('product_variants.id');
            })
            ->count();
    }

    /**
     * Multiply decimal quantity string (e.g. "12.500") by integer cost minor (e.g. 120000).
     * Result is integer minor (rounded).
     */
    public function multiplyQuantityCost(string $quantity, int $costMinor): int
    {
        // quantity is decimal:3 string, cost is int minor. Use bcmul with scale 3 then round.
        // Example: "10.500" * 10000 = 105000.000 -> 105000
        $product = bcmul($quantity, (string) $costMinor, 3);

        return (int) round((float) $product);
    }
}
