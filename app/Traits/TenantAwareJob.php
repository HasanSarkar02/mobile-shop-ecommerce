<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;

trait TenantAwareJob
{
    public ?int $tenantId = null;

    /**
     * Call this in the __construct() of your Job classes
     */
    protected function recordTenantContext(): void
    {
        if ($tenant = tenant()) {
            $this->tenantId = $tenant->id;
        }
    }

    /**
     * Call this at the very top of the handle() method of your Job classes
     */
    protected function restoreTenantContext(): void
    {
        if ($this->tenantId) {
            $tenant = Tenant::withoutGlobalScope('tenant')->find($this->tenantId);
            if ($tenant) {
                app(Tenancy::class)->set($tenant);
            }
        }
    }
}
