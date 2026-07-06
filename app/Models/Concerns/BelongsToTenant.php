<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::creating(function ($model): void {
            if (! $model->tenant_id && tenant()) {
                $model->tenant_id = tenant()->id;
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder): void {
            if (tenant()) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', tenant()->id);
            }
        });
    }
}