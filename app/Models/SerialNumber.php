<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SerialNumberStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class SerialNumber extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'product_variant_id','location_id', 'imei_or_serial', 'status',
        'warranty_start_at', 'warranty_end_at', 'sold_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => SerialNumberStatus::class,
            'warranty_start_at' => 'datetime',
            'warranty_end_at' => 'datetime',
            'sold_at' => 'datetime',
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
}