<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Domain;
use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $central = config('tenancy.central_domain');

        if ($host === $central || $host === "www.{$central}") {
            return $next($request);
        }

        $tenant = str_ends_with($host, ".{$central}")
            ? Tenant::query()->where('subdomain', substr($host, 0, -strlen(".{$central}")))->first()
            : Domain::query()->where('domain', $host)->whereNotNull('verified_at')->first()?->tenant;

        abort_unless($tenant?->isActive(), 404);

        app(Tenancy::class)->set($tenant);

        return $next($request);
    }
}