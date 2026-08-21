<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BackorderPolicy;
use App\Enums\FulfillmentStrategy;
use App\Enums\InventoryType;
use App\Enums\SerialNumberStatus;
use App\Enums\StockAdjustmentReason;
use App\Enums\StockMovementType;
use App\Enums\StockStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Location;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\SerialNumber;
use App\Models\StockItem;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for available quantity, purchasability, reservation,
 * backorder decisions, and stock status. No other code path computes these independently.
 */
class InventoryService
{
    public function defaultLocation(): Location
    {
        $location = Location::query()->where('is_default', true)->first();

        if ($location) {
            return $location;
        }

        $tenantId = tenant()?->id;

        if (! $tenantId) {
            throw new \RuntimeException('No default location found and no tenant context available.');
        }

        return Location::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'is_default' => true],
            ['name' => 'Main Store', 'type' => 'store', 'is_default' => true, 'is_active' => true],
        );
    }

    private function stockItemFor(ProductVariant $variant, ?Location $location = null): StockItem
    {
        $location ??= $this->defaultLocation();

        return StockItem::query()
            ->where('product_variant_id', $variant->id)
            ->where('location_id', $location->id)
            ->firstOrFail();
    }

    /**
     * Lock the stock_items for the given variants in ascending
     * product_variant_id order and return them. Every path that touches more
     * than one inventory row MUST call this before mutating so all lock
     * acquisition follows the same deterministic total order — never cart
     * iteration or order-item insertion order. Variants without a stock item
     * (preorder / dropship / not tracked) contribute nothing and are ignored.
     *
     * @param  Collection<int, ProductVariant>  $variants
     * @return Collection<int, StockItem>
     */
    public function lockStockItemsForVariants(Collection $variants): Collection
    {
        if ($variants->isEmpty()) {
            return collect();
        }

        $location = $this->defaultLocation();
        $ids = $variants->pluck('id')->sort()->values();

        return StockItem::query()
            ->whereIn('product_variant_id', $ids->all())
            ->where('location_id', $location->id)
            ->orderBy('product_variant_id')
            ->lockForUpdate()
            ->get();
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

    /**
     * Batch resolver for storefront display state. Mirrors stockStatus() and
     * availableQuantity() but reads stock from a single pre-loaded stock-item
     * map keyed by variant id, so a tracked variant that has no stock_item row
     * resolves as out-of-stock instead of throwing (stockItemFor uses
     * firstOrFail). Never call this per variant in a loop — pass the full
     * collection once.
     *
     * @param  Collection<int, ProductVariant>  $variants
     * @return Collection<int, array{stock_status: StockStatus, available_quantity: int, low_stock_threshold: ?int}> keyed by variant id
     */
    public function resolvePurchaseStates(Collection $variants): Collection
    {
        $ids = $variants->pluck('id')->values()->all();

        $location = Location::query()->where('is_default', true)->first();

        $stockItems = collect();
        if ($location !== null && $ids !== []) {
            $stockItems = StockItem::query()
                ->whereIn('product_variant_id', $ids)
                ->where('location_id', $location->id)
                ->get()
                ->keyBy('product_variant_id');
        }

        $defaultThreshold = (int) config('inventory.default_low_stock_threshold', 5);

        return $variants->mapWithKeys(function (ProductVariant $variant) use ($stockItems, $defaultThreshold): array {
            if ($variant->availability->value === 'discontinued') {
                return [$variant->id => ['stock_status' => StockStatus::Discontinued, 'available_quantity' => 0, 'low_stock_threshold' => null]];
            }

            if ($variant->fulfillment_strategy === FulfillmentStrategy::Preorder) {
                return [$variant->id => ['stock_status' => StockStatus::Preorder, 'available_quantity' => 0, 'low_stock_threshold' => null]];
            }

            if ($variant->fulfillment_strategy === FulfillmentStrategy::Dropship) {
                return [$variant->id => ['stock_status' => StockStatus::Dropship, 'available_quantity' => 0, 'low_stock_threshold' => null]];
            }

            $stockItem = $stockItems->get($variant->id);
            $available = $stockItem?->availableQuantity() ?? 0;

            if ($available <= 0) {
                return [$variant->id => [
                    'stock_status' => StockStatus::OutOfStock,
                    'available_quantity' => $available,
                    'low_stock_threshold' => $stockItem?->low_stock_threshold,
                ]];
            }

            $threshold = $stockItem?->low_stock_threshold ?? $variant->low_stock_threshold ?? $defaultThreshold;

            return [$variant->id => [
                'stock_status' => $available <= $threshold ? StockStatus::LowStock : StockStatus::InStock,
                'available_quantity' => $available,
                'low_stock_threshold' => $stockItem?->low_stock_threshold,
            ]];
        });
    }

    public function isPurchasable(ProductVariant $variant, int $quantity = 1, ?Location $location = null): bool
    {
        if ($variant->availability->value === 'discontinued') {
            return false;
        }

        if ($variant->fulfillment_strategy !== FulfillmentStrategy::Stock) {
            return true;
        }

        if ($variant->backorder_policy !== null && $variant->backorder_policy !== BackorderPolicy::Deny) {
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
        $bypassCheck = $variant->backorder_policy !== null && $variant->backorder_policy !== BackorderPolicy::Deny;

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
        });
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
        });
    }

    public function commit(ProductVariant $variant, int $quantity, ?Location $location = null, mixed $reference = null, ?OrderItem $orderItem = null): void
    {
        if ($variant->fulfillment_strategy !== FulfillmentStrategy::Stock) {
            return;
        }

        $location ??= $this->defaultLocation();

        if ($variant->inventory_type === InventoryType::Serialized) {
            if ($orderItem === null) {
                throw new \InvalidArgumentException('Serialized stock commits require an order item for exact serial attribution.');
            }

            $this->commitSerialized($variant, $quantity, $location, $reference, $orderItem);

            return;
        }

        $stockItem = $this->stockItemFor($variant, $location);

        DB::transaction(function () use ($variant, $quantity, $location, $stockItem, $reference): void {
            DB::table('stock_items')->where('id', $stockItem->id)->update([
                'quantity' => DB::raw('GREATEST(0, quantity - '.(int) $quantity.')'),
                'reserved_quantity' => DB::raw('GREATEST(0, reserved_quantity - '.(int) $quantity.')'),
            ]);

            $this->logMovement($variant, $location, StockMovementType::Sale, -$quantity, $reference);
        });
    }

    private function commitSerialized(ProductVariant $variant, int $quantity, Location $location, mixed $reference, OrderItem $orderItem): void
    {
        DB::transaction(function () use ($variant, $quantity, $location, $reference, $orderItem): void {
            // Global lock hierarchy: stock_items are locked before serial_numbers
            // (serial numbers are child rows of the variant's stock pool). The
            // stock row is locked first, then the exact serials in ascending id
            // order, so serialized commits can never acquire rows in an order
            // that conflicts with another inventory path.
            $stockItem = $this->stockItemFor($variant, $location);

            DB::table('stock_items')
                ->where('id', $stockItem->id)
                ->lockForUpdate()
                ->first();

            $serials = $variant->serialNumbers()
                ->where('status', SerialNumberStatus::Available->value)
                ->orderBy('id')
                ->lockForUpdate()
                ->limit($quantity)
                ->get();

            if ($serials->count() < $quantity) {
                throw new InsufficientStockException("Insufficient serialized stock for variant {$variant->sku}.");
            }

            foreach ($serials as $serial) {
                $serial->update([
                    'status' => SerialNumberStatus::Sold->value,
                    'sold_at' => now(),
                    'location_id' => $location->id,
                    'order_item_id' => $orderItem->id,
                ]);
            }

            DB::table('stock_items')->where('id', $stockItem->id)
                ->update([
                    'reserved_quantity' => DB::raw('GREATEST(0, CAST(reserved_quantity AS SIGNED) - '.(int) $quantity.')'),
                ]);

            $this->logMovement($variant, $location, StockMovementType::Sale, -$quantity, $reference);
        });
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

    /**
     * Return inventory to the sellable pool after an order cancellation.
     * Non-serialized tracked stock is incremented back and logged as a
     * Return movement; serialized stock returns the exact serials that were
     * sold against the cancelled order item. Preorder/dropship/not-tracked
     * variants are no-ops.
     *
     * @param  mixed  $reference  the cancelled order (used for audit linkage)
     * @return Collection<int, SerialNumber> the returned serials (empty for non-serialized)
     */
    public function restockFromCancellation(ProductVariant $variant, int $quantity, ?Location $location = null, mixed $reference = null, ?OrderItem $orderItem = null): Collection
    {
        if ($variant->fulfillment_strategy !== FulfillmentStrategy::Stock) {
            return collect();
        }

        $location ??= $this->defaultLocation();

        if ($variant->inventory_type === InventoryType::Serialized) {
            if ($orderItem === null) {
                throw new \InvalidArgumentException('Serialized stock returns require the order item for exact serial attribution.');
            }

            return $this->returnSoldSerials($variant, $quantity, $orderItem, $location, $reference);
        }

        $stockItem = $this->stockItemFor($variant, $location);

        DB::transaction(function () use ($variant, $quantity, $location, $stockItem, $reference): void {
            DB::table('stock_items')->where('id', $stockItem->id)->increment('quantity', $quantity);

            $this->logMovement($variant, $location, StockMovementType::Return, $quantity, $reference, null, 'Restocked from cancelled order');
        });

        return collect();
    }

    /**
     * Return the exact serials that were sold against an order item to the
     * available pool when the consuming order is cancelled. Serial-to-order
     * attribution is stored on serial_numbers.order_item_id at commit time, so
     * no "most recently sold" inference is used. If fewer sold serials are
     * found for the order item than the cancelled quantity, the operation
     * fails loudly instead of silently returning the wrong units.
     *
     * @param  mixed  $reference  the cancelled order (used for audit linkage)
     * @return Collection<int, SerialNumber> the returned serials
     */
    public function returnSoldSerials(ProductVariant $variant, int $quantity, OrderItem $orderItem, ?Location $location = null, mixed $reference = null): Collection
    {
        $location ??= $this->defaultLocation();

        return DB::transaction(function () use ($variant, $quantity, $location, $reference, $orderItem): Collection {
            $serials = $variant->serialNumbers()
                ->where('status', SerialNumberStatus::Sold->value)
                ->where('order_item_id', $orderItem->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->limit($quantity)
                ->get();

            if ($serials->count() < $quantity) {
                throw new InsufficientStockException(
                    "Cannot return serialized stock for variant {$variant->sku}: found {$serials->count()} of the {$quantity} serials sold to order item {$orderItem->id}."
                );
            }

            foreach ($serials as $serial) {
                $serial->update([
                    'status' => SerialNumberStatus::Available->value,
                    'sold_at' => null,
                    'location_id' => $location->id,
                    'order_item_id' => null,
                ]);
            }

            $this->logMovement($variant, $location, StockMovementType::Return, $quantity, $reference);

            return $serials;
        });
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
