<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscription extends Model
{
    protected $fillable = ['tenant_id', 'plan_id', 'status', 'current_period_starts_at', 'current_period_ends_at', 'cancelled_at'];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
        return $this->status === SubscriptionStatus::Trialing;
    }

    public function daysRemaining(): int
    {
        return max(0, (int) now()->diffInDays($this->current_period_ends_at, false));
    }
}