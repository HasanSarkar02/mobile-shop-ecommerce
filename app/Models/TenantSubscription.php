<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'current_period_starts_at',
        'current_period_ends_at',
        'cancelled_at',
        'plan_name',
        'billing_period',
        'price',
        'max_products',
        'max_staff',
        'custom_domain_allowed',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'price' => 'integer',
            'max_products' => 'integer',
            'max_staff' => 'integer',
            'custom_domain_allowed' => 'boolean',
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

    public function isTrialing(): bool
    {
        $status = $this->getAttribute('status');

        return $status instanceof SubscriptionStatus
            && $status === SubscriptionStatus::Trialing;
    }

    public function daysRemaining(): int
    {
        return max(0, (int) now()->diffInDays($this->current_period_ends_at, false));
    }

    /**
     * Snapshot entitlement value for a plan attribute. The snapshot written at
     * subscription time is authoritative; when a legacy row has no snapshot, the
     * current catalog plan is used as a fallback.
     */
    public function entitlement(string $attribute): mixed
    {
        $value = $this->getAttribute($attribute);

        if ($value !== null) {
            return $value;
        }

        $planAttribute = $attribute === 'plan_name' ? 'name' : $attribute;

        return $this->plan?->getAttribute($planAttribute);
    }
}
