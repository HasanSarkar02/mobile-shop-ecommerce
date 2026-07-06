<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Tenant;

final class Tenancy
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }
}