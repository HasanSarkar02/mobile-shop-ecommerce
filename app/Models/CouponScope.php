<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CouponScope extends Model
{
    protected $fillable = ['coupon_id', 'scopable_type', 'scopable_id'];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function scopable(): MorphTo
    {
        return $this->morphTo();
    }
}
