<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'order_id', 'order_fulfillment_id', 'product_variant_id', 'product_name_snapshot', 'variant_sku_snapshot',
        'unit_price', 'unit_cost_price', 'quantity', 'line_total', 'line_cost', 'unit_weight_grams', 'line_weight_grams', 'fulfillment_strategy', 'expected_available_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'unit_cost_price' => 'integer',
            'quantity' => 'decimal:3',
            'line_total' => 'integer',
            'line_cost' => 'integer',
            'unit_weight_grams' => 'integer',
            'line_weight_grams' => 'integer',
            'expected_available_at' => 'datetime',
        ];
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(OrderFulfillment::class, 'order_fulfillment_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return HasMany<SerialNumber, $this>
     */
    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class, 'order_item_id');
    }
}
