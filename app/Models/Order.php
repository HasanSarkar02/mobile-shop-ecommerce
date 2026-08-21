<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\PreventsDeletion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property-read OrderStatus $status
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, OrderPayment> $payments
 * @property-read Collection<int, OrderFulfillment> $fulfillments
 * @property-read Collection<int, OrderEvent> $events
 * @property-read Collection<int, StockMovement> $stockMovements
 */
class Order extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use PreventsDeletion;

    protected $fillable = [
        'tenant_id',
        'order_number', 'invoice_number', 'customer_id', 'guest_name', 'guest_email', 'guest_phone',
        'status', 'order_source', 'sales_channel', 'payment_method_id', 'shipping_method_id',
        'currency_code', 'currency_rate', 'subtotal', 'shipping_cost', 'discount_total', 'tax_total', 'grand_total',
        'shipping_address_id', 'shipping_address_snapshot', 'billing_address_id', 'billing_address_snapshot',
        'customer_note', 'internal_note', 'placed_at', 'reservation_expires_at',
        'active_reservation_key', 'preorder_ack_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'order_source' => OrderSource::class,
            'currency_rate' => 'decimal:6',
            'subtotal' => 'integer',
            'shipping_cost' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'grand_total' => 'integer',
            'shipping_address_snapshot' => 'array',
            'billing_address_snapshot' => 'array',
            'placed_at' => 'datetime',
            'reservation_expires_at' => 'datetime',
            'preorder_ack_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(OrderFulfillment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->latest('created_at');
    }

    /** @return MorphMany<StockMovement, $this> */
    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function customerDisplayName(): string
    {
        return $this->customer?->name ?? $this->guest_name ?? 'Guest';
    }

    public function hasExpiredReservation(): bool
    {
        return $this->status === OrderStatus::Pending
            && $this->reservation_expires_at !== null
            && $this->reservation_expires_at->isPast();
    }
}
