<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Tenancy\Tenancy;
use App\Support\Tenancy\TenantContextResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function __construct(private readonly TenantContextResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        app(Tenancy::class)->set(null);

        $host = $this->resolver->normalizeHost($request->getHost());

        abort_unless($this->resolver->isAllowedHost($host), 404);

        $tenant = $this->resolver->resolve($host);

        abort_unless($tenant !== null || $this->resolver->isCentralHost($host), 404);

        $scheme = $this->effectiveScheme($request);

        URL::forceRootUrl($scheme.'://'.$request->getHttpHost());

        URL::forceScheme($scheme);

        app(Tenancy::class)->set($tenant);

        return $next($request);
    }

    /**
     * Scheme used for generated URLs. FORCE_HTTPS wins outright, then the
     * request's own secure status, then the X-Forwarded-Proto header when a
     * trusted proxy is configured. Laravel's TrustProxies middleware runs
     * after this middleware, so forwarded headers are not yet applied to the
     * request and must be inspected manually.
     */
    private function effectiveScheme(Request $request): string
    {
        if (config('deployment.force_https')) {
            return 'https';
        }

        if ($request->secure()) {
            return 'https';
        }

        if (config('deployment.trusted_proxies') !== []) {
            $forwarded = $request->header('X-Forwarded-Proto');

            if (is_string($forwarded) && strtolower(trim(explode(',', $forwarded)[0])) === 'https') {
                return 'https';
            }
        }

        return 'http';
    }
}
