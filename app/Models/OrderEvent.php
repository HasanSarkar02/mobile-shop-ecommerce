<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderEventType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only order timeline. Immutable, same discipline as StockMovement.
 */
class OrderEvent extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'order_id', 'type', 'from_status', 'to_status', 'description', 'metadata', 'created_by'];

    protected function casts(): array
    {
        return [
            'type' => OrderEventType::class,
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Order events are immutable and can never be updated.');
    }

    public function delete(): bool
    {
        throw new \LogicException('Order events are immutable and can never be deleted.');
    }
}
