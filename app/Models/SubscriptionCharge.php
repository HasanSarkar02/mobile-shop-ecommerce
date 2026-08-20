<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionChargeStatus;
use App\Enums\SubscriptionDiscountType;
use App\Enums\SubscriptionPaymentIntent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A billing obligation for a subscription period. All money amounts are
 * integer minor units (paisa) and are frozen at creation: base_amount,
 * discount_amount and net_amount never change when the plan price changes.
 * SubscriptionPayment rows record the money actually received against it
 * (payment allocation is introduced in Phase 2).
 */
class SubscriptionCharge extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'intent',
        'period_starts_at',
        'period_ends_at',
        'base_amount',
        'discount_type',
        'discount_value',
        'discount_amount',
        'net_amount',
        'paid_amount',
        'status',
        'reference',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'intent' => SubscriptionPaymentIntent::class,
            'discount_type' => SubscriptionDiscountType::class,
            'status' => SubscriptionChargeStatus::class,
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
            'base_amount' => 'integer',
            'discount_value' => 'integer',
            'discount_amount' => 'integer',
            'net_amount' => 'integer',
            'paid_amount' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function outstandingAmount(): int
    {
        return max(0, (int) $this->net_amount - (int) $this->paid_amount);
    }

    public function isOpen(): bool
    {
        return $this->getAttribute('status') === SubscriptionChargeStatus::Open;
    }

    public function isPartiallyPaid(): bool
    {
        return $this->getAttribute('status') === SubscriptionChargeStatus::PartiallyPaid;
    }

    public function isPaid(): bool
    {
        return $this->getAttribute('status') === SubscriptionChargeStatus::Paid;
    }
}
