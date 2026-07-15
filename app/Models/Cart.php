<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    use BelongsToTenant;

    protected $fillable = [
    'tenant_id',
    'customer_id',
    'session_token',
    'currency_code',
    'converted_at',
];

    protected function casts(): array
    {
        return ['converted_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function isConverted(): bool
    {
        return $this->converted_at !== null;
    }
}