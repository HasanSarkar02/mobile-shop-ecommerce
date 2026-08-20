<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Enums\DeploymentMode;
use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LogicException;

final class TenantContextResolver
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function mode(): DeploymentMode
    {
        return DeploymentMode::tryFrom((string) config('deployment.mode', DeploymentMode::SaaS->value))
            ?? throw new LogicException('Invalid deployment mode configured.');
    }

    public function normalizeHost(string $host): string
    {
        return rtrim(strtolower(trim($host)), '.');
    }

    public function resolve(string $host): ?Tenant
    {
        $host = $this->normalizeHost($host);

        return $this->mode() === DeploymentMode::Dedicated
            ? $this->resolveDedicated($host)
            : $this->resolveSaaS($host);
    }

    public function isCentralHost(string $host): bool
    {
        $host = $this->normalizeHost($host);

        return $this->mode() === DeploymentMode::SaaS
            && ($host === $this->centralHost() || $host === 'www.'.$this->centralHost());
    }

    public function isReservedSubdomain(string $subdomain): bool
    {
        return in_array($subdomain, array_map(
            fn (mixed $reserved): string => $this->normalizeHost((string) $reserved),
            (array) config('tenancy.reserved_subdomains', []),
        ), true);
    }

    public function isAllowedHost(string $host): bool
    {
        $host = $this->normalizeHost($host);

        if ($this->mode() === DeploymentMode::Dedicated) {
            return $this->dedicatedHost() !== null && $host === $this->dedicatedHost();
        }

        if ($this->isCentralHost($host)) {
            return true;
        }

        if ($this->isSaaSSubdomain($host)) {
            return ! $this->isReservedSubdomain($this->saasSubdomainOf($host));
        }

        if ($this->matchesConfiguredHost($host)) {
            return true;
        }

        return Domain::query()
            ->where('domain', $host)
            ->orWhere('normalized_domain', $host)
            ->exists();
    }

    /**
     * Host patterns used by Laravel's TrustHosts middleware. Registered
     * database domains remain dynamic until the domain-management phase exists.
     *
     * @return list<string>
     */
    public function trustedHostPatterns(): array
    {
        if ($this->mode() === DeploymentMode::Dedicated) {
            $host = $this->dedicatedHost();

            return $host === null ? [] : [$this->exactHostPattern($host)];
        }

        $central = $this->centralHost();
        $patterns = [
            $this->exactHostPattern($central),
            $this->exactHostPattern('www.'.$central),
            '^([a-z0-9-]+\.)'.preg_quote($central, '/').'$',
        ];

        foreach ($this->configuredHosts() as $host) {
            $patterns[] = $this->hostPattern($host);
        }

        /** @var Collection<int, string> $domains */
        $domains = Domain::query()->pluck('domain');

        foreach ($domains as $domain) {
            $patterns[] = $this->exactHostPattern($this->normalizeHost($domain));
        }

        return array_values(array_unique($patterns));
    }

    public function tenantHost(Tenant $tenant): string
    {
        if ($this->mode() === DeploymentMode::Dedicated) {
            return $this->dedicatedHost() ?? throw new LogicException('Dedicated canonical host is not configured.');
        }

        return $this->normalizeHost($tenant->subdomain.'.'.$this->centralHost());
    }

    public function centralHost(): string
    {
        return $this->normalizeHost((string) config('tenancy.central_domain'));
    }

    public function dedicatedHost(): ?string
    {
        $host = config('deployment.dedicated.canonical_host');

        return is_string($host) && $host !== '' ? $this->normalizeHost($host) : null;
    }

    private function resolveSaaS(string $host): ?Tenant
    {
        if ($this->isCentralHost($host)) {
            return null;
        }

        if ($this->isSaaSSubdomain($host)) {
            $subdomain = $this->saasSubdomainOf($host);

            if ($this->isReservedSubdomain($subdomain)) {
                return null;
            }

            $tenant = Tenant::query()->where('subdomain', $subdomain)->first();

            return $tenant !== null && $this->subscriptions->hasEligibleSubscription($tenant) ? $tenant : null;
        }

        try {
            $normalized = DomainHostname::normalize($host);
        } catch (InvalidArgumentException) {
            return null;
        }

        $domain = Domain::query()
            ->where('domain', $normalized)
            ->orWhere('normalized_domain', $normalized)
            ->first();

        if ($domain === null
            || $this->domainStatus($domain) !== DomainStatus::Active
            || $domain->getAttribute('verified_at') === null
        ) {
            return null;
        }

        $tenantId = $domain->getAttribute('tenant_id');
        $tenant = is_numeric($tenantId) ? Tenant::query()->find((int) $tenantId) : null;

        if (! $tenant?->isActive()) {
            return null;
        }

        return $this->subscriptions->canUseCustomDomain($tenant) ? $tenant : null;
    }

    private function domainStatus(Domain $domain): ?DomainStatus
    {
        $status = $domain->getAttribute('status');

        return $status instanceof DomainStatus
            ? $status
            : DomainStatus::tryFrom((string) $status);
    }

    private function resolveDedicated(string $host): ?Tenant
    {
        if ($this->dedicatedHost() === null || $host !== $this->dedicatedHost()) {
            return null;
        }

        $tenantId = config('deployment.dedicated.tenant_id');

        if (! is_numeric($tenantId)) {
            return null;
        }

        $tenant = Tenant::query()->find((int) $tenantId);

        return $tenant?->isActive() ? $tenant : null;
    }

    private function isSaaSSubdomain(string $host): bool
    {
        $suffix = '.'.$this->centralHost();

        if (! str_ends_with($host, $suffix)) {
            return false;
        }

        $subdomain = substr($host, 0, -strlen($suffix));

        return $subdomain !== '' && ! str_contains($subdomain, '.');
    }

    private function saasSubdomainOf(string $host): string
    {
        return substr($host, 0, -strlen('.'.$this->centralHost()));
    }

    /** @return list<string> */
    private function configuredHosts(): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $host): string => $this->normalizeHost((string) $host),
            (array) config('deployment.allowed_hosts', []),
        )));
    }

    private function matchesConfiguredHost(string $host): bool
    {
        foreach ($this->configuredHosts() as $configured) {
            if ($configured === $host) {
                return true;
            }

            if (str_starts_with($configured, '*.')) {
                $suffix = substr($configured, 1);

                if (str_ends_with($host, $suffix) && $host !== substr($configured, 2)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function exactHostPattern(string $host): string
    {
        return '^'.preg_quote($host, '/').'$';
    }

    private function hostPattern(string $host): string
    {
        if (str_starts_with($host, '*.')) {
            return '^([a-z0-9-]+\.)+'.preg_quote(substr($host, 2), '/').'$';
        }

        return $this->exactHostPattern($host);
    }
}
