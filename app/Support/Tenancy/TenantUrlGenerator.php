<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Enums\DeploymentMode;
use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;

final class TenantUrlGenerator
{
    public function __construct(
        private readonly TenantContextResolver $resolver,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function storefront(Tenant $tenant, string $path = '/'): string
    {
        $path = '/'.ltrim($path, '/');
        $locale = function_exists('app') ? app()->getLocale() : 'en';
        if ($locale === 'bn' && $tenant->supportsLocale('bn')) {
            $path = '/bn'.($path === '/' ? '' : $path);
        }

        return $this->absolute($this->resolver->tenantHost($tenant), $path);
    }

    public function admin(Tenant $tenant): string
    {
        return $this->canonicalPath($tenant, '/admin');
    }

    public function platform(string $path = '/platform'): string
    {
        return $this->absolute($this->resolver->centralHost(), $path);
    }

    public function canonicalHost(Tenant $tenant): string
    {
        if ($this->resolver->mode() === DeploymentMode::Dedicated) {
            return $this->resolver->tenantHost($tenant);
        }

        $primaryId = Tenant::query()->whereKey($tenant->id)->value('primary_domain_id');

        if (! is_numeric($primaryId) || ! $tenant->isActive()) {
            return $this->resolver->tenantHost($tenant);
        }

        $domain = Domain::query()->find((int) $primaryId);

        if ($domain === null
            || (int) $domain->getAttribute('tenant_id') !== (int) $tenant->id
            || $this->domainStatus($domain) !== DomainStatus::Active
            || $domain->getAttribute('verified_at') === null
            || ! $this->subscriptions->canUseCustomDomain($tenant)
        ) {
            return $this->resolver->tenantHost($tenant);
        }

        $normalized = $domain->getAttribute('normalized_domain');

        if (! is_string($normalized) || $normalized === '') {
            return $this->resolver->tenantHost($tenant);
        }

        try {
            return DomainHostname::normalize($normalized);
        } catch (InvalidArgumentException) {
            return $this->resolver->tenantHost($tenant);
        }
    }

    public function canonicalPath(Tenant $tenant, string $path = '/'): string
    {
        $path = '/'.ltrim($path, '/');
        // Prefix /bn when current locale is bn and tenant supports it (Phase A).
        // English stays at root per decision; no query-string locale.
        $locale = function_exists('app') ? app()->getLocale() : 'en';
        if ($locale === 'bn' && $tenant->supportsLocale('bn')) {
            $path = '/bn'.($path === '/' ? '' : $path);
        }

        return $this->absolute($this->canonicalHost($tenant), $path);
    }

    /** @param array<string|int, mixed> $parameters */
    public function canonicalRoute(Tenant $tenant, string $route, array $parameters = []): string
    {
        $locale = function_exists('app') ? app()->getLocale() : 'en';
        if ($locale === 'bn' && $tenant->supportsLocale('bn') && Route::has('bn.'.$route)) {
            return $this->absolute($this->canonicalHost($tenant), route('bn.'.$route, $parameters, false));
        }

        return $this->absolute($this->canonicalHost($tenant), route($route, $parameters, false));
    }

    private function absolute(string $host, string $path): string
    {
        $scheme = (bool) config('deployment.force_https')
            ? 'https'
            : (string) config('deployment.url_scheme', 'http');

        return rtrim($scheme.'://'.$host, '/').'/'.ltrim($path, '/');
    }

    private function domainStatus(Domain $domain): ?DomainStatus
    {
        $status = $domain->getAttribute('status');

        return $status instanceof DomainStatus
            ? $status
            : DomainStatus::tryFrom((string) $status);
    }
}
