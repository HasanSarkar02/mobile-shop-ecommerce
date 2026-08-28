<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BdDistrict extends Model
{
    protected $fillable = [
        'division_id',
        'name_en',
        'name_bn',
        'bbs_code',
        'url',
        'lat',
        'lon',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lon' => 'decimal:7',
        ];
    }

    /**
     * @return BelongsTo<BdDivision, BdDistrict>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(BdDivision::class, 'division_id');
    }

    /**
     * @return HasMany<BdUpazila>
     */
    public function upazilas(): HasMany
    {
        return $this->hasMany(BdUpazila::class, 'district_id');
    }
}
