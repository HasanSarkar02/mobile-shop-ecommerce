<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DeploymentMode;
use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\DomainHostname;
use App\Support\Tenancy\DomainVerificationChallenge;
use App\Support\Tenancy\TenantContextResolver;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DomainManagementService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly TenantContextResolver $resolver,
    ) {}

    public function createPendingDomain(Tenant $tenant, string $hostname, User $actor): DomainVerificationChallenge
    {
        $this->authorizeTenant($tenant, $actor);
        $normalized = DomainHostname::normalize($hostname);

        if (! $this->subscriptions->canUseCustomDomain($tenant)) {
            throw new DomainException('The tenant is not entitled to use custom domains.');
        }

        $this->assertNoDuplicate($normalized);

        return DB::transaction(function () use ($tenant, $normalized, $actor): DomainVerificationChallenge {
            $challenge = $this->newChallenge($normalized);

            $domain = Domain::query()->create([
                'tenant_id' => $tenant->id,
                'domain' => $normalized,
                'normalized_domain' => $normalized,
                'status' => DomainStatus::Pending,
                'verification_method' => 'dns_txt',
                'verification_token_digest' => $challenge['digest'],
                'verification_record_name' => $challenge['record_name'],
                'verification_started_at' => now(),
                'verification_expires_at' => $challenge['expires_at'],
                'verification_attempts' => 0,
            ]);

            $this->log($domain, 'domain.created', $actor, [
                'domain' => $normalized,
                'status' => DomainStatus::Pending->value,
            ]);
            $this->log($domain, 'domain.verification_initiated', $actor, [
                'verification_method' => 'dns_txt',
                'verification_record_name' => $challenge['record_name'],
                'verification_expires_at' => $challenge['expires_at']->toIso8601String(),
            ]);

            return new DomainVerificationChallenge(
                $domain,
                $challenge['record_name'],
                $challenge['value'],
                $challenge['expires_at'],
            );
        });
    }

    public function regenerateVerificationChallenge(Domain $domain, User $actor): DomainVerificationChallenge
    {
        $tenant = $this->tenantFor($domain);
        $this->authorizeTenant($tenant, $actor);
        $domain = $this->freshDomain($domain);

        $status = $this->statusOf($domain);

        if (! in_array($status, [DomainStatus::Pending, DomainStatus::Verified, DomainStatus::Failed], true)) {
            throw new DomainException('Verification can only be regenerated for a pending, verified, or failed domain.');
        }

        $normalized = $domain->normalized_domain ?: DomainHostname::normalize($domain->domain);
        $challenge = $this->newChallenge($normalized);

        DB::transaction(function () use ($domain, $challenge, $actor): void {
            $locked = Domain::query()->whereKey($domain->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($locked, DomainStatus::Pending);

            $locked->update([
                'normalized_domain' => $challenge['domain'],
                'domain' => $challenge['domain'],
                'status' => DomainStatus::Pending,
                'verification_method' => 'dns_txt',
                'verification_token_digest' => $challenge['digest'],
                'verification_record_name' => $challenge['record_name'],
                'verification_started_at' => now(),
                'verification_expires_at' => $challenge['expires_at'],
                'verification_attempts' => 0,
                'last_checked_at' => null,
                'verification_failure_code' => null,
                'verification_failure_message' => null,
                'verified_at' => null,
                'activated_at' => null,
            ]);

            $this->log($locked, 'domain.verification_regenerated', $actor, [
                'verification_record_name' => $challenge['record_name'],
                'verification_expires_at' => $challenge['expires_at']->toIso8601String(),
            ]);
        });

        return new DomainVerificationChallenge(
            $domain->fresh(),
            $challenge['record_name'],
            $challenge['value'],
            $challenge['expires_at'],
        );
    }

    public function markVerified(Domain $domain, string $challengeValue, User $actor): Domain
    {
        $tenant = $this->tenantFor($domain);
        $this->authorizeTenant($tenant, $actor);
        $domain = $this->freshDomain($domain);

        $status = $this->statusOf($domain);

        if (! in_array($status, [DomainStatus::Pending, DomainStatus::Failed], true)) {
            throw new DomainException('Only pending or failed domains can be verified.');
        }

        $expiresAt = $domain->getAttribute('verification_expires_at');
        $digest = $domain->getAttribute('verification_token_digest');

        if (! $expiresAt instanceof CarbonInterface || $expiresAt->isPast()) {
            $this->recordVerificationFailure($domain, 'challenge_expired', 'The verification challenge has expired.', $actor);

            throw new DomainException('The verification challenge has expired.');
        }

        if ($digest === null || $challengeValue === '' || ! hash_equals($digest, hash('sha256', $challengeValue))) {
            $this->recordVerificationFailure($domain, 'invalid_challenge', 'The supplied verification challenge is invalid.', $actor);

            throw new DomainException('The supplied verification challenge is invalid.');
        }

        return DB::transaction(function () use ($domain, $actor): Domain {
            $locked = Domain::query()->whereKey($domain->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($locked, DomainStatus::Verified);

            $locked->update([
                'status' => DomainStatus::Verified,
                'verified_at' => now(),
                'last_checked_at' => now(),
                'verification_attempts' => $locked->verification_attempts + 1,
                'verification_failure_code' => null,
                'verification_failure_message' => null,
            ]);

            $this->log($locked, 'domain.verified', $actor, [
                'verification_method' => $locked->verification_method,
            ]);

            return $locked->fresh();
        });
    }

    public function recordVerificationFailure(
        Domain $domain,
        string $failureCode,
        string $failureMessage,
        User $actor,
    ): Domain {
        $tenant = $this->tenantFor($domain);
        $this->authorizeTenant($tenant, $actor);

        return DB::transaction(function () use ($domain, $failureCode, $failureMessage, $actor): Domain {
            $locked = Domain::query()->whereKey($domain->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($locked, DomainStatus::Failed);

            $locked->update([
                'status' => DomainStatus::Failed,
                'last_checked_at' => now(),
                'verification_attempts' => $locked->verification_attempts + 1,
                'verification_failure_code' => $failureCode,
                'verification_failure_message' => $failureMessage,
            ]);

            $this->log($locked, 'domain.verification_failed', $actor, [
                'failure_code' => $failureCode,
                'failure_message' => $failureMessage,
            ]);

            return $locked->fresh();
        });
    }

    public function activate(Domain $domain, User $actor): Domain
    {
        $tenant = $this->tenantFor($domain);
        $this->authorizeTenant($tenant, $actor);

        if ($this->statusOf($domain) === DomainStatus::Active) {
            return $domain->fresh();
        }

        if (! in_array($this->statusOf($domain), [DomainStatus::Verified, DomainStatus::Suspended], true)
            || $domain->verified_at === null
        ) {
            throw new DomainException('Only verified domains can be activated.');
        }

        if (! $this->subscriptions->canUseCustomDomain($tenant)) {
            throw new DomainException('The tenant is not entitled to use custom domains.');
        }

        return DB::transaction(function () use ($domain, $actor): Domain {
            $locked = Domain::query()->whereKey($domain->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($locked, DomainStatus::Active);

            $locked->update([
                'status' => DomainStatus::Active,
                'activated_at' => now(),
                'revoked_at' => null,
                'revocation_reason' => null,
            ]);

            $this->log($locked, 'domain.activated', $actor);

            return $locked->fresh();
        });
    }

    public function suspend(Domain $domain, User $actor): Domain
    {
        $tenant = $this->tenantFor($domain);
        $this->authorizeTenant($tenant, $actor);

        if ($this->statusOf($domain) === DomainStatus::Suspended) {
            return $domain->fresh();
        }

        return DB::transaction(function () use ($domain, $tenant, $actor): Domain {
            $locked = Domain::query()->whereKey($domain->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($locked, DomainStatus::Suspended);

            $locked->update(['status' => DomainStatus::Suspended]);
            $this->clearPrimaryIf($tenant, $locked);
            $this->log($locked, 'domain.suspended', $actor);

            return $locked->fresh();
        });
    }

    public function revoke(Domain $domain, User $actor, ?string $reason = null): Domain
    {
        $tenant = $this->tenantFor($domain);
        $this->authorizeTenant($tenant, $actor);

        if ($this->statusOf($domain) === DomainStatus::Revoked) {
            return $domain->fresh();
        }

        return DB::transaction(function () use ($domain, $tenant, $actor, $reason): Domain {
            $locked = Domain::query()->whereKey($domain->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($locked, DomainStatus::Revoked);

            $locked->update([
                'status' => DomainStatus::Revoked,
                'revoked_at' => now(),
                'revocation_reason' => $reason,
            ]);
            $this->clearPrimaryIf($tenant, $locked);
            $this->log($locked, 'domain.revoked', $actor, ['reason' => $reason]);

            return $locked->fresh();
        });
    }

    public function setPrimary(Domain $domain, User $actor): Tenant
    {
        $tenant = $this->tenantFor($domain);
        $this->authorizeTenant($tenant, $actor);

        if ($this->statusOf($domain) !== DomainStatus::Active || $domain->verified_at === null) {
            throw new DomainException('Only verified active domains can become primary.');
        }

        if (! $this->subscriptions->canUseCustomDomain($tenant)) {
            throw new DomainException('The tenant is not entitled to use custom domains.');
        }

        return DB::transaction(function () use ($domain, $tenant, $actor): Tenant {
            $locked = Domain::query()->whereKey($domain->id)->lockForUpdate()->firstOrFail();
            $lockedTenant = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            $previousId = $lockedTenant->getAttribute('primary_domain_id');

            if ((int) $previousId === (int) $locked->id) {
                return $lockedTenant->fresh();
            }

            Tenant::query()
                ->whereKey($lockedTenant->id)
                ->update(['primary_domain_id' => $locked->id]);
            $this->log($locked, 'domain.primary_changed', $actor, [
                'previous_primary_domain_id' => $previousId,
                'primary_domain_id' => $locked->id,
            ]);

            return $lockedTenant->fresh();
        });
    }

    public function clearPrimaryDomain(Domain $domain, User $actor): Tenant
    {
        $tenant = $this->tenantFor($domain);
        $this->authorizeTenant($tenant, $actor);

        return DB::transaction(function () use ($domain, $tenant, $actor): Tenant {
            $locked = Domain::query()->whereKey($domain->id)->lockForUpdate()->firstOrFail();
            $lockedTenant = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            if ((int) $lockedTenant->getAttribute('primary_domain_id') !== (int) $locked->id) {
                return $lockedTenant->fresh();
            }

            $lockedTenant->newQuery()->whereKey($lockedTenant->id)->update(['primary_domain_id' => null]);
            $this->log($locked, 'domain.primary_cleared', $actor);

            return $lockedTenant->fresh();
        });
    }

    public function removePendingDomain(Domain $domain, User $actor): void
    {
        $tenant = $this->tenantFor($domain);
        $this->authorizeTenant($tenant, $actor);

        if (! in_array($this->statusOf($domain), [DomainStatus::Pending, DomainStatus::Failed], true)) {
            throw new DomainException('Only pending or failed domains can be removed.');
        }

        DB::transaction(function () use ($domain, $actor): void {
            $locked = Domain::query()->whereKey($domain->id)->lockForUpdate()->firstOrFail();
            $status = $this->statusOf($locked);

            if (! in_array($status, [DomainStatus::Pending, DomainStatus::Failed], true)) {
                throw new DomainException('Only pending or failed domains can be removed.');
            }

            $this->log($locked, 'domain.pending_removed', $actor);
            $locked->delete();
        });
    }

    private function authorizeTenant(Tenant $tenant, User $actor): void
    {
        if ($this->resolver->mode() === DeploymentMode::Dedicated) {
            throw new AuthorizationException('SaaS domain management is unavailable in Dedicated mode.');
        }

        if ($actor->is_platform_admin) {
            return;
        }

        if ($actor->isOwner() && (int) $actor->tenant_id === (int) $tenant->id) {
            return;
        }

        throw new AuthorizationException('You are not authorized to manage this tenant domain.');
    }

    private function assertNoDuplicate(string $normalized): void
    {
        $duplicate = Domain::query()
            ->where('normalized_domain', $normalized)
            ->orWhere('domain', $normalized)
            ->exists();

        if ($duplicate) {
            throw new InvalidArgumentException('This hostname is already registered.');
        }
    }

    /** @return array{domain: string, value: string, digest: string, record_name: string, expires_at: CarbonImmutable} */
    private function newChallenge(string $normalized): array
    {
        $value = bin2hex(random_bytes(32));
        $expiresAt = CarbonImmutable::now()->addHours((int) config(
            'domain-verification.challenge_ttl_hours',
            config('tenancy.domain_verification_ttl_hours', 72),
        ));
        $prefix = rtrim((string) config(
            'domain-verification.record_prefix',
            config('tenancy.domain_verification_record_prefix', '_mobile-shop-verification'),
        ), '.');

        return [
            'domain' => $normalized,
            'value' => $value,
            'digest' => hash('sha256', $value),
            'record_name' => $prefix.'.'.$normalized,
            'expires_at' => $expiresAt,
        ];
    }

    private function tenantFor(Domain $domain): Tenant
    {
        $tenantId = $domain->getAttribute('tenant_id');

        if (! is_numeric($tenantId)) {
            throw (new ModelNotFoundException)->setModel(Tenant::class, [$tenantId]);
        }

        return Tenant::query()->findOrFail((int) $tenantId);
    }

    private function freshDomain(Domain $domain): Domain
    {
        return Domain::query()->findOrFail($domain->id);
    }

    private function assertTransition(Domain $domain, DomainStatus $to): void
    {
        if (! $this->statusOf($domain)->canTransitionTo($to)) {
            throw new DomainException('Invalid domain status transition.');
        }
    }

    private function statusOf(Domain $domain): DomainStatus
    {
        $status = $domain->getAttribute('status');

        if ($status instanceof DomainStatus) {
            return $status;
        }

        return DomainStatus::tryFrom((string) $status)
            ?? throw new DomainException('Domain has an invalid lifecycle status.');
    }

    private function clearPrimaryIf(Tenant $tenant, Domain $domain): void
    {
        $lockedTenant = Tenant::query()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();

        if ((int) $lockedTenant->getAttribute('primary_domain_id') === (int) $domain->id) {
            $lockedTenant->newQuery()->whereKey($lockedTenant->id)->update(['primary_domain_id' => null]);
        }
    }

    /** @param array<string, mixed> $properties */
    private function log(Domain $domain, string $event, User $actor, array $properties = []): void
    {
        activity('domains')
            ->performedOn($domain)
            ->causedBy($actor)
            ->event($event)
            ->withProperties(array_merge([
                'tenant_id' => $domain->getAttribute('tenant_id'),
                'domain_id' => $domain->id,
            ], $properties))
            ->log($event);
    }
}
