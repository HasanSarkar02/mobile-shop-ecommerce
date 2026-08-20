<?php

use App\Http\Middleware\AssignStorefrontTokens;
use App\Http\Middleware\EnsureCentralDomain;
use App\Http\Middleware\EnsureTenant;
use App\Http\Middleware\IdentifyTenant;
use App\Http\Middleware\ResolveSupportSession;
use App\Support\Tenancy\TenantContextResolver;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', '')),
        )));
        $trustedProxyHeaders = match (strtolower((string) env('TRUSTED_PROXY_HEADERS', 'all'))) {
            'host' => Request::HEADER_X_FORWARDED_HOST,
            'proto' => Request::HEADER_X_FORWARDED_PROTO,
            'port' => Request::HEADER_X_FORWARDED_PORT,
            'for' => Request::HEADER_X_FORWARDED_FOR,
            default => Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        };

        $middleware->trustHosts(
            at: fn (): array => app(TenantContextResolver::class)->trustedHostPatterns(),
            subdomains: false,
        );
        $middleware->trustProxies(
            at: $trustedProxies,
            headers: $trustedProxyHeaders,
        );
        $middleware->prepend(IdentifyTenant::class);

        $middleware->web(append: [
            AssignStorefrontTokens::class,
            ResolveSupportSession::class,
        ]);

        $middleware->alias([
            'tenant' => EnsureTenant::class,
            'central' => EnsureCentralDomain::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'payment/success',
            'payment/fail',
            'payment/cancel',
            'payment/ipn',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
