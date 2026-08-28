<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantShippingRate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'bd_division_id',
        'bd_district_id',
        'bd_upazila_id',
        'charge',
        'free_threshold',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'charge' => 'integer',
            'free_threshold' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BdDivision, TenantShippingRate>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(BdDivision::class, 'bd_division_id');
    }

    /**
     * @return BelongsTo<BdDistrict, TenantShippingRate>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(BdDistrict::class, 'bd_district_id');
    }

    /**
     * @return BelongsTo<BdUpazila, TenantShippingRate>
     */
    public function upazila(): BelongsTo
    {
        return $this->belongsTo(BdUpazila::class, 'bd_upazila_id');
    }
}
