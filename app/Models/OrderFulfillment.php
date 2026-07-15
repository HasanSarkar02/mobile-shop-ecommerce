<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderFulfillmentStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFulfillment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['order_id', 'location_id', 'status', 'tracking_number', 'courier_name', 'shipped_at', 'delivered_at'];

    protected function casts(): array
    {
        return [
            'status' => OrderFulfillmentStatus::class,
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}