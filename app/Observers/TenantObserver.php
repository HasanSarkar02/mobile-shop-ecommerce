<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Location;
use App\Models\Tenant;

class TenantObserver
{
    public function created(Tenant $tenant): void
    {
        Location::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Store',
            'type' => 'store',
            'is_default' => true,
            'is_active' => true,
        ]);
    }
}