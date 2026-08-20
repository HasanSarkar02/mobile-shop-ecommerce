<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionPaymentIntent;
use App\Enums\SubscriptionPaymentStatus;
use App\Models\Concerns\PreventsDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A platform subscription payment. This is deliberately separate from
 * TenantSubscription: payment providers report a verified payment result
 * here and only SubscriptionService ever mutates the subscription itself.
 * Money amounts are stored as integer minor units (paisa).
 */
class SubscriptionPayment extends Model
{
    use PreventsDeletion;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'intent',
        'subscription_charge_id',
        'extension_days',
        'status',
        'provider',
        'payment_method',
        'currency',
        'amount',
        'reference',
        'note',
        'received_at',
        'rejected_at',
        'rejected_reason',
        'created_by',
        'verified_by',
        'rejected_by',
    ];

    protected function casts(): array
    {
        return [
            'intent' => SubscriptionPaymentIntent::class,
            'status' => SubscriptionPaymentStatus::class,
            'extension_days' => 'integer',
            'amount' => 'integer',
            'received_at' => 'datetime',
            'rejected_at' => 'datetime',
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

    public function charge(): BelongsTo
    {
        return $this->belongsTo(SubscriptionCharge::class, 'subscription_charge_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
