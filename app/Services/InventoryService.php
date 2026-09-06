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
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    /**
     * Lock products rows for the given variants in ascending product_id order.
     * Every path that mutates sold_quantity for a multi-variant order MUST call
     * this before the per-variant commit loop so all product-row locks follow the
     * same deterministic total order — aggregation by product_id happens outside
     * the per-variant helper. Tenant-scoped to avoid cross-tenant races.
     *
     * @param  Collection<int, ProductVariant>  $variants
     * @return Collection<int, \App\Models\Product>
     */
    public function lockProductsForVariants(Collection $variants): Collection
    {
        if ($variants->isEmpty()) {
            return collect();
        }

        $productIds = $variants->pluck('product_id')->filter()->unique()->sort()->values();

        if ($productIds->isEmpty()) {
            return collect();
        }

        return \App\Models\Product::query()
            ->whereIn('id', $productIds->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Aggregate quantities by product_id (DECIMAL strings, SCALE=3) for a
     * collection of order items / variants. Used to ensure sold_quantity is
     * updated once per product, not once per variant, when an order touches
     * multiple variants of the same product.
     *
     * @param  Collection<int, \App\Models\OrderItem|array>  $items
     * @return array<int, string> product_id => quantity string "3.750"
     */
    public function aggregateQuantitiesByProduct(Collection $items): array
    {
        $map = [];

        foreach ($items as $item) {
            $productId = null;
            $qty = '0.000';

            if ($item instanceof \App\Models\OrderItem) {
                $productId = $item->variant?->product_id ?? null;
                if ($productId === null && isset($item->product_variant_id)) {
                    $productId = \App\Models\ProductVariant::query()->whereKey($item->product_variant_id)->value('product_id');
                }
                $qty = $this->normalizeQty($item->quantity);
            } elseif (is_array($item)) {
                $productId = $item['product_id'] ?? $item['productId'] ?? null;
                $qty = $this->normalizeQty($item['quantity'] ?? '0.000');
            }

            if ($productId === null) {
                continue;
            }

            $map[$productId] = isset($map[$productId])
                ? bcadd($map[$productId], $qty, \App\Models\StockItem::SCALE)
                : $qty;
        }

        return $map;
    }

    public function availableQuantity(ProductVariant $variant, ?Location $location = null): int
    {
        return $this->stockItemFor($variant, $location)->availableQuantity();
    }

    /**
     * Decimal-canonical availability for measured goods (Phase C-1): a
     * fixed-point STRING at scale 3 ("3.750") computed by bcmath inside
     * StockItem — never a float. Discrete goods return whole-number strings.
     */
    public function availableDecimal(ProductVariant $variant, ?Location $location = null): string
    {
        return $this->stockItemFor($variant, $location)->availableQuantityDecimal();
    }

    /**
     * Normalize any caller-supplied quantity (int | float | numeric-string)
     * into a fixed-point string at scale 3. The ONLY place floats touch this
     * service is this input-formatting step; every subsequent comparison or
     * mutation goes through bccomp/bcadd/bcsub on these strings. The output
     * always matches ^-?\d+\.\d{3}$ so it is also safe to interpolate into
     * raw SQL expressions.
     */
    private function normalizeQty(int|float|string $quantity): string
    {
        if (! is_numeric($quantity)) {
            throw new \InvalidArgumentException('Stock quantities must be numeric.');
        }

        return number_format((float) $quantity, 3, '.', '');
    }

    private function gtZero(string $value): bool
    {
        return bccomp($value, '0', StockItem::SCALE) === 1;
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
        $available = $stockItem->availableQuantityDecimal();

        if (! $this->gtZero($available)) {
            return StockStatus::OutOfStock;
        }

        $threshold = (string) ($stockItem->low_stock_threshold
            ?? $variant->low_stock_threshold
            ?? (int) config('inventory.default_low_stock_threshold', 5));

        return bccomp($available, $threshold, StockItem::SCALE) !== 1 ? StockStatus::LowStock : StockStatus::InStock;
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

        // Use tenant-scoped default location (creates it if missing) so single-variant
        // cards never resolve as OutOfStock just because stock_items lookup missed the
        // tenant's location. Previous raw where('is_default') without tenant could
        // return null and silently mark every variant as 0 stock → CTA disabled.
        try {
            $location = $this->defaultLocation();
        } catch (\Throwable) {
            $location = Location::query()->where('is_default', true)->first();
        }

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
                return [$variant->id => ['stock_status' => StockStatus::Discontinued, 'available_quantity' => 0, 'available_quantity_decimal' => '0.000', 'low_stock_threshold' => null]];
            }

            if ($variant->fulfillment_strategy === FulfillmentStrategy::Preorder) {
                return [$variant->id => ['stock_status' => StockStatus::Preorder, 'available_quantity' => 0, 'available_quantity_decimal' => '0.000', 'low_stock_threshold' => null]];
            }

            if ($variant->fulfillment_strategy === FulfillmentStrategy::Dropship) {
                return [$variant->id => ['stock_status' => StockStatus::Dropship, 'available_quantity' => 0, 'available_quantity_decimal' => '0.000', 'low_stock_threshold' => null]];
            }

            $stockItem = $stockItems->get($variant->id);
            $availableDecimal = $stockItem?->availableQuantityDecimal() ?? '0.000';
            // Keep both int (backward compat) and decimal string. Decimal is
            // authoritative for measured goods (0.750 kg) where (int) truncation
            // would wrongly report 0 and force isPurchasable false → disabled CTA
            // for single-variant products. Status already uses bccomp on decimal.
            $available = (int) $availableDecimal;
            $itemThreshold = $stockItem !== null && $stockItem->low_stock_threshold !== null
                ? (int) $stockItem->low_stock_threshold
                : null;

            if (! $this->gtZero($availableDecimal)) {
                return [$variant->id => [
                    'stock_status' => StockStatus::OutOfStock,
                    'available_quantity' => $available,
                    'available_quantity_decimal' => $availableDecimal,
                    'low_stock_threshold' => $itemThreshold,
                ]];
            }

            $threshold = (string) ($itemThreshold ?? $variant->low_stock_threshold ?? $defaultThreshold);

            return [$variant->id => [
                'stock_status' => bccomp($availableDecimal, $threshold, StockItem::SCALE) !== 1 ? StockStatus::LowStock : StockStatus::InStock,
                'available_quantity' => $available,
                'available_quantity_decimal' => $availableDecimal,
                'low_stock_threshold' => $itemThreshold,
            ]];
        });
    }

    public function isPurchasable(ProductVariant $variant, int|float|string $quantity = 1): bool
    {
        $facts = $this->purchasabilityFacts(collect([$variant]))[$variant->id];

        if ($facts['discontinued'] || $facts['non_stock']) {
            return $this->purchasability()->evaluate(
                discontinued: $facts['discontinued'],
                nonStock: $facts['non_stock'],
                serialized: $facts['serialized'],
                backorderAllowed: $facts['backorder_allowed'],
                availableQuantity: '0.000',
                serialsAvailable: $facts['serials_available'],
                quantity: $quantity,
            );
        }

        // Decimal-safe availability: measured goods report "3.750" while discrete
        // "5.000" still compares correctly via bccomp at scale 3. Missing
        // stock row (tracked variant never stocked) is treated as 0.
        try {
            $available = $this->availableDecimal($variant);
        } catch (ModelNotFoundException) {
            $available = '0.000';
        }

        return $this->purchasability()->evaluate(
            discontinued: $facts['discontinued'],
            nonStock: $facts['non_stock'],
            serialized: $facts['serialized'],
            backorderAllowed: $facts['backorder_allowed'],
            availableQuantity: $available,
            serialsAvailable: $facts['serials_available'],
            quantity: $quantity,
        );
    }

    /**
     * Batched purchasability FACTS (not decisions) per variant: the raw
     * inputs PurchasabilityPolicy evaluates. Enum/model knowledge stays
     * inside InventoryService; every consumer feeds plain scalars to the
     * shared rule so no surface ever re-implements it.
     *
     * @param  Collection<int, ProductVariant>  $variants
     * @return Collection<int, array{discontinued: bool, non_stock: bool, serialized: bool, backorder_allowed: bool, serials_available: int}>
     */
    public function purchasabilityFacts(Collection $variants): Collection
    {
        $serialCounts = $this->availableSerialCounts($variants);

        return $variants->mapWithKeys(function (ProductVariant $variant) use ($serialCounts): array {
            return [$variant->id => [
                'discontinued' => $variant->availability->value === 'discontinued',
                'non_stock' => $variant->fulfillment_strategy !== FulfillmentStrategy::Stock,
                'serialized' => $variant->inventory_type === InventoryType::Serialized,
                'backorder_allowed' => $variant->backorder_policy !== null && $variant->backorder_policy !== BackorderPolicy::Deny,
                'serials_available' => (int) $serialCounts->get($variant->id, 0),
            ]];
        });
    }

    /**
     * Available serial counts for a batch of variants, keyed by variant id —
     * lets listing surfaces feed PurchasabilityPolicy without per-variant queries.
     *
     * @param  Collection<int, ProductVariant>  $variants
     * @return Collection<int, int>
     */
    public function availableSerialCounts(Collection $variants): Collection
    {
        $serializedIds = $variants
            ->filter(fn (ProductVariant $variant): bool => $variant->inventory_type === InventoryType::Serialized)
            ->pluck('id')
            ->values();

        if ($serializedIds->isEmpty()) {
            return collect();
        }

        return SerialNumber::query()
            ->whereIn('product_variant_id', $serializedIds)
            ->where('status', SerialNumberStatus::Available->value)
            ->selectRaw('product_variant_id, COUNT(*) as available_count')
            ->groupBy('product_variant_id')
            ->pluck('available_count', 'product_variant_id');
    }

    private function purchasability(): PurchasabilityPolicy
    {
        return new PurchasabilityPolicy;
    }

    public function reserve(ProductVariant $variant, int|float|string $quantity, ?Location $location = null, mixed $reference = null): void
    {
        if ($variant->fulfillment_strategy !== FulfillmentStrategy::Stock) {
            return;
        }

        $location ??= $this->defaultLocation();
        $stockItem = $this->stockItemFor($variant, $location);
        $bypassCheck = $variant->backorder_policy !== null && $variant->backorder_policy !== BackorderPolicy::Deny;
        $qty = $this->normalizeQty($quantity);

        DB::transaction(function () use ($variant, $qty, $location, $stockItem, $reference, $bypassCheck): void {
            $query = DB::table('stock_items')->where('id', $stockItem->id);

            if (! $bypassCheck) {
                // bcmath-safe guard: the sanitized fixed-point string is bound
                // as a parameter so MySQL compares DECIMAL exactly.
                $query->whereRaw('(quantity - reserved_quantity) >= ?', [$qty]);
            }

            $affected = $query->update([
                'reserved_quantity' => DB::raw('reserved_quantity + '.$qty),
            ]);

            if ($affected === 0) {
                throw new InsufficientStockException("Insufficient stock for variant {$variant->sku}.");
            }

            $this->logMovement($variant, $location, StockMovementType::Reservation, '-'.$qty, $reference);
        });
    }

    public function release(ProductVariant $variant, int|float|string $quantity, ?Location $location = null, mixed $reference = null): void
    {
        if ($variant->fulfillment_strategy !== FulfillmentStrategy::Stock) {
            return;
        }

        $location ??= $this->defaultLocation();
        $stockItem = $this->stockItemFor($variant, $location);
        $qty = $this->normalizeQty($quantity);

        DB::transaction(function () use ($variant, $qty, $location, $stockItem, $reference): void {
            DB::table('stock_items')->where('id', $stockItem->id)
                ->update(['reserved_quantity' => DB::raw('GREATEST(0, reserved_quantity - '.$qty.')')]);

            $this->logMovement($variant, $location, StockMovementType::Release, $qty, $reference);
        });
    }

    public function commit(ProductVariant $variant, int|float|string $quantity, ?Location $location = null, mixed $reference = null, ?OrderItem $orderItem = null): void
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
        $qty = $this->normalizeQty($quantity);

        DB::transaction(function () use ($variant, $qty, $location, $stockItem, $reference): void {
            DB::table('stock_items')->where('id', $stockItem->id)->update([
                'quantity' => DB::raw('GREATEST(0, quantity - '.$qty.')'),
                'reserved_quantity' => DB::raw('GREATEST(0, reserved_quantity - '.$qty.')'),
            ]);

            $this->logMovement($variant, $location, StockMovementType::Sale, '-'.$qty, $reference);

            // Atomic sold_quantity increment with deterministic product lock (tenant-scoped).
            // Decision 2/3: DECIMAL(10,3) sold_quantity increments atomically with Sale.
            $productId = $variant->product_id ?? \App\Models\ProductVariant::query()->whereKey($variant->id)->value('product_id');
            if ($productId !== null) {
                DB::table('products')->where('id', $productId)->where('tenant_id', $variant->tenant_id)->lockForUpdate()->first();
                DB::table('products')->where('id', $productId)->where('tenant_id', $variant->tenant_id)->update([
                    'sold_quantity' => DB::raw("sold_quantity + ".$qty),
                ]);
            }
        });
    }

    private function commitSerialized(ProductVariant $variant, int|float|string $quantity, Location $location, mixed $reference, OrderItem $orderItem): void
    {
        $qtyInt = (int) $this->normalizeQty($quantity);
        $qtyDec = $this->normalizeQty($quantity);

        DB::transaction(function () use ($variant, $qtyInt, $qtyDec, $location, $reference, $orderItem): void {
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
                ->limit($qtyInt)
                ->get();

            if ($serials->count() < $qtyInt) {
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
                    'reserved_quantity' => DB::raw('GREATEST(0, CAST(reserved_quantity AS SIGNED) - '.(int) $qtyInt.')'),
                ]);

            $this->logMovement($variant, $location, StockMovementType::Sale, -$qtyInt, $reference);

            // Atomic sold_quantity increment for serialized (discrete units, integer qty).
            $productId = $variant->product_id ?? \App\Models\ProductVariant::query()->whereKey($variant->id)->value('product_id');
            if ($productId !== null) {
                DB::table('products')->where('id', $productId)->where('tenant_id', $variant->tenant_id)->lockForUpdate()->first();
                DB::table('products')->where('id', $productId)->where('tenant_id', $variant->tenant_id)->update([
                    'sold_quantity' => DB::raw("sold_quantity + ".$qtyDec),
                ]);
            }
        });
    }

    public function restock(ProductVariant $variant, int|float|string $quantity, ?Location $location = null, ?string $comment = null): void
    {
        $this->guardNotSerialized($variant);

        $location ??= $this->defaultLocation();
        $stockItem = $this->stockItemFor($variant, $location);
        $qty = $this->normalizeQty($quantity);

        DB::transaction(function () use ($variant, $qty, $location, $stockItem, $comment): void {
            DB::table('stock_items')->where('id', $stockItem->id)
                ->update(['quantity' => DB::raw('quantity + '.$qty)]);

            $this->logMovement($variant, $location, StockMovementType::Restock, $qty, null, null, $comment);
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
    public function restockFromCancellation(ProductVariant $variant, int|float|string $quantity, ?Location $location = null, mixed $reference = null, ?OrderItem $orderItem = null): Collection
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
        $qty = $this->normalizeQty($quantity);

        DB::transaction(function () use ($variant, $qty, $location, $stockItem, $reference): void {
            DB::table('stock_items')->where('id', $stockItem->id)
                ->update(['quantity' => DB::raw('quantity + '.$qty)]);

            $this->logMovement($variant, $location, StockMovementType::Return, $qty, $reference, null, 'Restocked from cancelled order');

            // Atomic sold_quantity decrement — single path for both cancellation and full refund+restock (decisions 4,7).
            $productId = $variant->product_id ?? \App\Models\ProductVariant::query()->whereKey($variant->id)->value('product_id');
            if ($productId !== null) {
                DB::table('products')->where('id', $productId)->where('tenant_id', $variant->tenant_id)->lockForUpdate()->first();
                DB::table('products')->where('id', $productId)->where('tenant_id', $variant->tenant_id)->update([
                    'sold_quantity' => DB::raw("GREATEST(0.000, sold_quantity - ".$qty.")"),
                ]);
            }
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
    public function returnSoldSerials(ProductVariant $variant, int|float|string $quantity, OrderItem $orderItem, ?Location $location = null, mixed $reference = null): Collection
    {
        $location ??= $this->defaultLocation();
        $qty = $this->normalizeQty($quantity);

        return DB::transaction(function () use ($variant, $qty, $location, $reference, $orderItem): Collection {
            $serials = $variant->serialNumbers()
                ->where('status', SerialNumberStatus::Sold->value)
                ->where('order_item_id', $orderItem->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->limit((int) $qty)
                ->get();

            if ($serials->count() < (int) $qty) {
                throw new InsufficientStockException(
                    "Cannot return serialized stock for variant {$variant->sku}: found {$serials->count()} of the {$qty} serials sold to order item {$orderItem->id}."
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

            $this->logMovement($variant, $location, StockMovementType::Return, $qty, $reference);

            // Atomic sold_quantity decrement for serialized — aggregated per product.
            $productId = $variant->product_id ?? \App\Models\ProductVariant::query()->whereKey($variant->id)->value('product_id');
            if ($productId !== null) {
                DB::table('products')->where('id', $productId)->where('tenant_id', $variant->tenant_id)->lockForUpdate()->first();
                DB::table('products')->where('id', $productId)->where('tenant_id', $variant->tenant_id)->update([
                    'sold_quantity' => DB::raw("GREATEST(0.000, sold_quantity - ".$qty.")"),
                ]);
            }

            return $serials;
        });
    }

    public function adjust(ProductVariant $variant, int|float|string $quantityChange, StockAdjustmentReason $reason, ?Location $location = null, ?string $comment = null): void
    {
        $this->guardNotSerialized($variant);

        $location ??= $this->defaultLocation();
        $stockItem = $this->stockItemFor($variant, $location);
        $qtyChange = $this->normalizeQty($quantityChange);

        DB::transaction(function () use ($variant, $qtyChange, $location, $stockItem, $reason, $comment): void {
            DB::table('stock_items')->where('id', $stockItem->id)
                ->update(['quantity' => DB::raw('GREATEST(0, quantity + ('.$qtyChange.'))')]);

            $this->logMovement($variant, $location, StockMovementType::Adjustment, $qtyChange, null, $reason, $comment);
        }, 3);
    }

    public function transitionToStock(ProductVariant $variant, int|float|string $initialQuantity, ?Location $location = null): void
    {
        $location ??= $this->defaultLocation();
        $qty = $this->normalizeQty($initialQuantity);

        DB::transaction(function () use ($variant, $qty, $location): void {
            $variant->update([
                'fulfillment_strategy' => FulfillmentStrategy::Stock,
                'inventory_type' => $variant->inventory_type === InventoryType::NotTracked ? InventoryType::Tracked : $variant->inventory_type,
            ]);

            DB::table('stock_items')
                ->where('product_variant_id', $variant->id)
                ->where('location_id', $location->id)
                ->update(['quantity' => $qty]);

            $this->logMovement($variant, $location, StockMovementType::Initial, $qty);
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
        int|float|string $quantityChange,
        mixed $reference = null,
        ?StockAdjustmentReason $reason = null,
        ?string $comment = null,
    ): void {
        $quantityAfter = (string) (StockItem::query()
            ->where('product_variant_id', $variant->id)
            ->where('location_id', $location->id)
            ->value('quantity') ?? '0.000');

        StockMovement::query()->create([
            'tenant_id' => $variant->tenant_id,
            'product_variant_id' => $variant->id,
            'location_id' => $location->id,
            'type' => $type,
            'quantity_change' => $this->normalizeQty($quantityChange),
            'quantity_after' => $this->normalizeQty($quantityAfter),
            'reason' => $reason,
            'comment' => $comment,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->id,
            'created_by' => auth()->id(),
        ]);
    }
}
