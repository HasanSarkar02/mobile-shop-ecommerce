<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockAdjustmentReason;
use App\Enums\StockMovementType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only audit ledger. Immutable by design: no updated_at, no soft deletes,
 * and update()/delete() are hard-blocked below regardless of caller.
 */
class StockMovement extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = [
        'product_variant_id', 'location_id', 'type', 'quantity_change', 'quantity_after',
        'reason', 'comment', 'reference_type', 'reference_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'reason' => StockAdjustmentReason::class,
            'quantity_change' => 'integer',
            'quantity_after' => 'integer',
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

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Stock movements are immutable and can never be updated.');
    }

    public function delete(): bool
    {
        throw new \LogicException('Stock movements are immutable and can never be deleted.');
    }
}