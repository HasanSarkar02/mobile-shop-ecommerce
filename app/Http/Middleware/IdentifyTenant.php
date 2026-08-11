<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Domain;
use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower(trim($request->getHost()));
        $central = strtolower(trim(config('tenancy.central_domain')));

        // Always generate URLs from the requesting host, never config('app.url').
        // Without this, post-login redirects (route()) resolve to the central
        // domain, where there is no tenant context and any Customer query throws.
        URL::forceRootUrl($request->getSchemeAndHttpHost());

        if ($host === $central || $host === "www.{$central}") {
            // Reset tenant for central domain
            app(Tenancy::class)->set(null);
            return $next($request);
        }

        // Tenant subdomain / custom domain logic
        $tenant = str_ends_with($host, ".{$central}")
            ? Tenant::query()->where('subdomain', substr($host, 0, -strlen(".{$central}")))->first()
            : Domain::query()->where('domain', $host)->first()?->tenant;

        abort_unless($tenant?->isActive(), 404);

        app(Tenancy::class)->set($tenant);

        return $next($request);
    }
}