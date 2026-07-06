<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;

if (! function_exists('tenant')) {
    function tenant(): ?Tenant
    {
        return app(Tenancy::class)->get();
    }
}