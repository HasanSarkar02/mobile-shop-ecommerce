<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierConnection extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'courier_provider_id', 'credentials', 'is_active', 'is_default', 'sandbox', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sandbox' => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(CourierProvider::class, 'courier_provider_id');
    }

    public function effectiveBaseUrl(): string
    {
        $provider = $this->relationLoaded('provider') ? $this->provider : $this->provider()->first();

        if (! $provider) {
            return '';
        }

        return $provider->effectiveBaseUrl((bool) $this->sandbox);
    }

    public function driverClass(): ?string
    {
        $provider = $this->relationLoaded('provider') ? $this->provider : $this->provider()->first();

        return $provider?->driver_class ?? config('couriers.drivers.'.$provider?->code);
    }
}
