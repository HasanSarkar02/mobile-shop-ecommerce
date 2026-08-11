<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionEventType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEvent extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'type', 'from_plan_id', 'to_plan_id', 'note'];

    protected function casts(): array
    {
        return ['type' => SubscriptionEventType::class];
    }

    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'from_plan_id');
    }

    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'to_plan_id');
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('Subscription events are immutable and can never be updated.');
    }

    public function delete(): bool
    {
        throw new \LogicException('Subscription events are immutable and can never be deleted.');
    }
}