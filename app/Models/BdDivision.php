<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BdDivision extends Model
{
    protected $fillable = [
        'name_en',
        'name_bn',
        'bn_name',
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
     * @return HasMany<BdDistrict>
     */
    public function districts(): HasMany
    {
        return $this->hasMany(BdDistrict::class, 'division_id');
    }
}
