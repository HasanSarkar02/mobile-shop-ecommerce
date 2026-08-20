<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;

/**
 * Public signup facade. Provisioning itself is delegated to the single
 * authoritative TenantBootstrapService so both the public signup and the
 * Platform Admin "Create Tenant" flow share identical core logic.
 */
class TenantRegistrationService
{
    /**
     * @return array{0: Tenant, 1: User}
     */
    public function register(string $businessName, string $subdomain, string $ownerName, string $ownerEmail, string $password): array
    {
        return app(TenantBootstrapService::class)->bootstrap([
            'name' => $businessName,
            'subdomain' => $subdomain,
            'plan' => 'trial',
            'owner' => [
                'name' => $ownerName,
                'email' => $ownerEmail,
                'password' => $password,
            ],
        ], ownerMode: TenantBootstrapService::OWNER_MODE_EXPLICIT);
    }
}
