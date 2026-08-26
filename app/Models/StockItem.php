<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockItem extends Model
{
    use BelongsToTenant;

    /** Canonical decimal scale for all stock math (Phase C-1). */
    public const SCALE = 3;

    protected $fillable = ['product_variant_id', 'location_id', 'quantity', 'reserved_quantity', 'low_stock_threshold'];

    protected function casts(): array
    {
        return [
            // decimal:3 cast returns exact fixed-point strings ("3.750") —
            // never floats — so callers can feed them straight into bc*().
            'quantity' => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
            'low_stock_threshold' => 'integer',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * The only place quantity - reserved_quantity is computed.
     * All callers (including InventoryService) must use this, never recompute inline.
     *
     * Decimal-canonical form (Phase C-1): returns a fixed-point STRING at
     * scale 3 via bcmath — e.g. "3.750" — clamped at "0.000". No floating-
     * point operators ever touch stock quantities.
     */
    public function availableQuantityDecimal(): string
    {
        $available = bcsub((string) $this->quantity, (string) $this->reserved_quantity, self::SCALE);

        return bccomp($available, '0', self::SCALE) === -1 ? '0.000' : $available;
    }

    /**
     * Integer fast path for discrete goods (pcs/pack/dozen and all legacy
     * rows without a measured UOM): whole-number quantities are exact, so a
     * plain int cast of the canonical decimal loses nothing.
     */
    public function availableQuantity(): int
    {
        return (int) $this->availableQuantityDecimal();
    }
}
