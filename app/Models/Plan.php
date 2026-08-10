<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['name', 'slug', 'price', 'billing_period', 'max_products', 'max_staff', 'custom_domain_allowed', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'max_products' => 'integer',
            'max_staff' => 'integer',
            'custom_domain_allowed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }
}