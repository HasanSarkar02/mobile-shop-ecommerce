<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CouponCustomerEligibility;
use App\Enums\CouponEligibilityScope;
use App\Enums\CouponScopeMode;
use App\Enums\CouponType;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\CouponCustomerEligibility as CouponCustomerEligibilityModel;

class Coupon extends Model
{
    use BelongsToTenant;
    use HasSchedule;

    protected $fillable = [
        'campaign_id', 'code', 'name', 'description', 'type', 'value', 'max_discount_amount',
        'min_order_amount', 'min_quantity', 'eligibility_scope', 'scope_mode', 'customer_eligibility',
        'usage_limit_total', 'usage_limit_per_customer', 'is_active', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'eligibility_scope' => CouponEligibilityScope::class,
            'scope_mode' => CouponScopeMode::class,
            'customer_eligibility' => CouponCustomerEligibility::class,
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $coupon): void {
            $coupon->code = $coupon->code ? strtoupper(trim($coupon->code)) : null;
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function customerEligibilities(): HasMany
    {
        return $this->hasMany(CouponCustomerEligibilityModel::class);
    }
    public function scopes(): HasMany
    {
        return $this->hasMany(CouponScope::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }
}