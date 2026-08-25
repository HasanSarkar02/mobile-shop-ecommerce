<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantUrlGenerator;
use Illuminate\Auth\Middleware\Authenticate as BaseAuthenticate;
use Illuminate\Http\Request;

class Authenticate extends BaseAuthenticate
{
    protected function redirectTo(Request $request): ?string
    {
        if (! $request->expectsJson()) {
            // For customer guard, redirect to storefront login with tenant-aware URL
            if (in_array('customer', $request->route()?->middleware() ?? [], true) || $request->is('account/*')) {
                $tenant = function_exists('tenant') ? tenant() : null;
                if ($tenant !== null) {
                    return app(TenantUrlGenerator::class)->canonicalRoute($tenant, 'storefront.login');
                }

                return route('storefront.login');
            }

            return route('storefront.login');
        }

        return null;
    }
}
