<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShippingMethodType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'type', 'cost', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'type' => ShippingMethodType::class,
            'cost' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
