<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderFulfillmentStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderFulfillment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'order_id', 'location_id', 'status', 'fulfillment_group', 'expected_available_at', 'tracking_number', 'courier_name', 'courier_connection_id', 'shipped_at', 'delivered_at'];

    protected function casts(): array
    {
        return [
            'status' => OrderFulfillmentStatus::class,
            'fulfillment_group' => 'string',
            'expected_available_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_fulfillment_id');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * The courier account this consignment was created through. Nullable: rows
     * shipped before the FK existed, or whose free-text courier name never
     * resolved to exactly one connection, keep NULL and fall back to name
     * matching. See the add_courier_connection_id migration.
     */
    public function courierConnection(): BelongsTo
    {
        return $this->belongsTo(CourierConnection::class);
    }
}
