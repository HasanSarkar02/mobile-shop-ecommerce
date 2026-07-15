<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'order_number', 'invoice_number', 'customer_id', 'guest_name', 'guest_email', 'guest_phone',
        'status', 'order_source', 'sales_channel', 'payment_method_id', 'shipping_method_id',
        'currency_code', 'currency_rate', 'subtotal', 'shipping_cost', 'discount_total', 'tax_total', 'grand_total',
        'shipping_address_id', 'shipping_address_snapshot', 'billing_address_id', 'billing_address_snapshot',
        'customer_note', 'internal_note', 'placed_at',
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

    public function customerDisplayName(): string
    {
        return $this->customer?->name ?? $this->guest_name ?? 'Guest';
    }
}