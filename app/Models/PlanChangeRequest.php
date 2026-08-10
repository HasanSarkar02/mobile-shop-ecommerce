<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlanChangeRequestStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanChangeRequest extends Model
{
    use BelongsToTenant;

    protected $fillable = ['requested_plan_id', 'status', 'note'];

    protected function casts(): array
    {
        return ['status' => PlanChangeRequestStatus::class];
    }

    public function requestedPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'requested_plan_id');
    }
}