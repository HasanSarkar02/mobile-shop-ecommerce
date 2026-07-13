<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FulfillmentStrategy;
use App\Enums\InventoryType;
use App\Enums\StockAdjustmentReason;
use App\Enums\StockMovementType;
use App\Enums\StockStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for available quantity, purchasability, reservation,
 * backorder decisions, and stock status. No other code path computes these independently.
 */
class InventoryService
{
    public function defaultLocation(): Location
    {
        return Location::query()->where('is_default', true)->firstOrFail();
    }

    private function stockItemFor(ProductVariant $variant, ?Location $location = null): StockItem
    {
        $location ??= $this->defaultLocation();

        return StockItem::query()
            ->where('product_variant_id', $variant->id)
            ->where('location_id', $location->id)
            ->firstOrFail();
    }

    public function availableQuantity(ProductVariant $variant, ?Location $location = null): int
    {
        return $this->stockItemFor($variant, $location)->availableQuantity();
    }

    public function stockStatus(ProductVariant $variant, ?Location $location = null): StockStatus
    {
        if ($variant->availability->value === 'discontinued') {
            return StockStatus::Discontinued;
        }

        if ($variant->fulfillment_strategy === FulfillmentStrategy::Preorder) {
            return StockStatus::Preorder;
        }

        if ($variant->fulfillment_strategy === FulfillmentStrategy::Dropship) {
            return StockStatus::Dropship;
        }

        $stockItem = $this->stockItemFor($variant, $location);
        $available = $stockItem->availableQuantity();

        if ($available <= 0) {
            return StockStatus::OutOfStock;
        }

        $threshold = $stockItem->low_stock_threshold
            ?? $variant->low_stock_threshold
            ?? (int) config('inventory.default_low_stock_threshold', 5);

        return $available <= $threshold ? StockStatus::LowStock : StockStatus::InStock;
    }

    public function isPurchasable(ProductVariant $variant, int $quantity = 1, ?Location $location = null): bool
    {
        if ($variant->availability->value === 'discontinued') {
            return false;
        }

        if ($variant->fulfillment_strategy !== FulfillmentStrategy::Stock) {
            return true;
        }

        if ($variant->backorder_policy !== null && $variant->backorder_policy !== \App\Enums\BackorderPolicy::Deny) {
            return true;
        }

        return $this->availableQuantity($variant, $location) >= $quantity;
    }

    public function reserve(ProductVariant $variant, int $quantity, ?Location $location = null, mixed $reference = null): void
    {
        if ($variant->fulfillment_strategy !== FulfillmentStrategy::Stock) {
            return;
        }

        $location ??= $this->defaultLocation();
        $stockItem = $this->stockItemFor($variant, $location);
        $bypassCheck = $variant->backorder_policy !== null && $variant->backorder_policy !== \App\Enums\BackorderPolicy::Deny;

        DB::transaction(function () use ($variant, $quantity, $location, $stockItem, $reference, $bypassCheck): void {
            $query = DB::table('stock_items')->where('id', $stockItem->id);

            if (! $bypassCheck) {
                $query->whereRaw('(quantity - reserved_quantity) >= ?', [$quantity]);
            }

            $affected = $query->increment('reserved_quantity', $quantity);

            if ($affected === 0) {
                throw new InsufficientStockException("Insufficient stock for variant {$variant->sku}.");
            }

            $this->logMovement($variant, $location, StockMovementType::Reservation, -$quantity, $reference);
        }, 3);
    }

    public function release(ProductVariant $variant, int $quantity, ?Location $location = null, mixed $reference = null): void
    {
        if ($variant->fulfillment_strategy !== FulfillmentStrategy::Stock) {
            return;
        }

        $location ??= $this->defaultLocation();
        $stockItem = $this->stockItemFor($variant, $location);

        DB::transaction(function () use ($variant, $quantity, $location, $stockItem, $reference): void {
            DB::table('stock_items')->where('id', $stockItem->id)
                ->update(['reserved_quantity' => DB::raw('GREATEST(0, reserved_quantity - '.(int) $quantity.')')]);

            $this->logMovement($variant, $location, StockMovementType::Release, $quantity, $reference);
        }, 3);
    }

    public function commit(ProductVariant $variant, int $quantity, ?Location $location = null, mixed $reference = null): void
    {
        if ($variant->fulfillment_strategy !== FulfillmentStrategy::Stock) {
            return;
        }

        $location ??= $this->defaultLocation();

        if ($variant->inventory_type === InventoryType::Serialized) {
            $this->commitSerialized($variant, $quantity, $location, $reference);

            return;
        }

        $stockItem = $this->stockItemFor($variant, $location);

        DB::transaction(function () use ($variant, $quantity, $location, $stockItem, $reference): void {
            DB::table('stock_items')->where('id', $stockItem->id)->update([
                'quantity' => DB::raw('GREATEST(0, quantity - '.(int) $quantity.')'),
                'reserved_quantity' => DB::raw('GREATEST(0, reserved_quantity - '.(int) $quantity.')'),
            ]);

            $this->logMovement($variant, $location, StockMovementType::Sale, -$quantity, $reference);
        }, 3);
    }

    private function commitSerialized(ProductVariant $variant, int $quantity, Location $location, mixed $reference): void
    {
        DB::transaction(function () use ($variant, $quantity, $location, $reference): void {
            $serials = $variant->serialNumbers()
                ->where('status', 'available')
                ->lockForUpdate()
                ->limit($quantity)
                ->get();

            if ($serials->count() < $quantity) {
                throw new InsufficientStockException("Insufficient serialized stock for variant {$variant->sku}.");
            }

            foreach ($serials as $serial) {
                $serial->update(['status' => 'sold', 'sold_at' => now(), 'location_id' => $location->id]);
            }

            $this->logMovement($variant, $location, StockMovementType::Sale, -$quantity, $reference);
        }, 3);
    }

    public function restock(ProductVariant $variant, int $quantity, ?Location $location = null, ?string $comment = null): void
    {
        $this->guardNotSerialized($variant);

        $location ??= $this->defaultLocation();
        $stockItem = $this->stockItemFor($variant, $location);

        DB::transaction(function () use ($variant, $quantity, $location, $stockItem, $comment): void {
            DB::table('stock_items')->where('id', $stockItem->id)->increment('quantity', $quantity);

            $this->logMovement($variant, $location, StockMovementType::Restock, $quantity, null, null, $comment);
        }, 3);
    }

    public function adjust(ProductVariant $variant, int $quantityChange, StockAdjustmentReason $reason, ?Location $location = null, ?string $comment = null): void
    {
        $this->guardNotSerialized($variant);

        $location ??= $this->defaultLocation();
        $stockItem = $this->stockItemFor($variant, $location);

        DB::transaction(function () use ($variant, $quantityChange, $location, $stockItem, $reason, $comment): void {
            DB::table('stock_items')->where('id', $stockItem->id)
                ->update(['quantity' => DB::raw('GREATEST(0, quantity + ('.(int) $quantityChange.'))')]);

            $this->logMovement($variant, $location, StockMovementType::Adjustment, $quantityChange, null, $reason, $comment);
        }, 3);
    }

    public function transitionToStock(ProductVariant $variant, int $initialQuantity, ?Location $location = null): void
    {
        $location ??= $this->defaultLocation();

        DB::transaction(function () use ($variant, $initialQuantity, $location): void {
            $variant->update([
                'fulfillment_strategy' => FulfillmentStrategy::Stock,
                'inventory_type' => $variant->inventory_type === InventoryType::NotTracked ? InventoryType::Tracked : $variant->inventory_type,
            ]);

            DB::table('stock_items')
                ->where('product_variant_id', $variant->id)
                ->where('location_id', $location->id)
                ->update(['quantity' => $initialQuantity]);

            $this->logMovement($variant, $location, StockMovementType::Initial, $initialQuantity);
        }, 3);
    }

    private function guardNotSerialized(ProductVariant $variant): void
    {
        if ($variant->inventory_type === InventoryType::Serialized) {
            throw new \LogicException('Serialized variants cannot be manually restocked/adjusted — manage via Serial Numbers instead.');
        }
    }

    private function logMovement(
        ProductVariant $variant,
        Location $location,
        StockMovementType $type,
        int $quantityChange,
        mixed $reference = null,
        ?StockAdjustmentReason $reason = null,
        ?string $comment = null,
    ): void {
        $quantityAfter = (int) (StockItem::query()
            ->where('product_variant_id', $variant->id)
            ->where('location_id', $location->id)
            ->value('quantity') ?? 0);

        StockMovement::query()->create([
            'tenant_id' => $variant->tenant_id,
            'product_variant_id' => $variant->id,
            'location_id' => $location->id,
            'type' => $type,
            'quantity_change' => $quantityChange,
            'quantity_after' => $quantityAfter,
            'reason' => $reason,
            'comment' => $comment,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->id,
            'created_by' => auth()->id(),
        ]);
    }
}