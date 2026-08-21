<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\TenantContextRequiredException;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
            if ($tenant = tenant()) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenant->id);

                return;
            }

            throw new TenantContextRequiredException(
                'Attempted to query '.$builder->getModel()::class.' with no resolved tenant context. '
                .'If this is a queued job, console command, or scheduled task, call '
                .'app(\App\Support\Tenancy\Tenancy::class)->set($tenant) before touching tenant-scoped models. '
                .'If this is a deliberate cross-tenant read (e.g. a platform-admin view), use '
                .'->withoutGlobalScope(\'tenant\') explicitly on that query instead of relying on this exception.'
            );
        });
    }

    /**
     * Get the tenant that owns the model.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
