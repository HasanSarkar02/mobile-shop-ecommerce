<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BdUpazila extends Model
{
    protected $fillable = [
        'district_id',
        'name_en',
        'name_bn',
    ];

    /**
     * @return BelongsTo<BdDistrict, BdUpazila>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(BdDistrict::class, 'district_id');
    }
}
