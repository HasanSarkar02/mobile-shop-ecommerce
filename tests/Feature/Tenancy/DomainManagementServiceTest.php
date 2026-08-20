<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\DomainManagementService;
use App\Support\Tenancy\DomainHostname;
use App\Support\Tenancy\Tenancy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

function phaseTwoDomainTenant(bool $entitled = true): array
{
    $tenant = Tenant::factory()->create([
        'subdomain' => 'domain-service-'.Str::lower(Str::random(8)),
        'status' => 'active',
    ]);
    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
    ]);
    $plan = Plan::query()->create([
        'name' => 'Domain Test Plan '.Str::random(8),
        'slug' => 'domain-test-'.Str::lower(Str::random(8)),
        'price' => 1000,
        'billing_period' => 'monthly',
        'custom_domain_allowed' => $entitled,
        'is_active' => true,
    ]);
    TenantSubscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'current_period_starts_at' => now()->subDay(),
        'current_period_ends_at' => now()->addMonth(),
    ]);

    return [$tenant, $owner];
}

beforeEach(function (): void {
    config()->set([
        'deployment.mode' => DeploymentMode::SaaS->value,
        'tenancy.domain_verification_ttl_hours' => 72,
        'tenancy.domain_verification_record_prefix' => '_mobile-shop-verification',
    ]);
    app(Tenancy::class)->set(null);
});

it('normalizes valid hostnames and rejects unsafe hostname input', function (): void {
    expect(DomainHostname::normalize('  Shop.Example.COM. '))->toBe('shop.example.com');

    if (function_exists('idn_to_ascii')) {
        expect(DomainHostname::normalize('münich.example.com'))->toBe('xn--mnich-kva.example.com');
    }

    foreach ([
        'https://shop.example.com',
        'shop.example.com/path',
        'shop.example.com?query=1',
        'shop.example.com:443',
        '*.example.com',
        '127.0.0.1',
        'localhost',
        'invalid..example.com',
        config('tenancy.central_domain'),
        'admin.'.config('tenancy.central_domain'),
    ] as $hostname) {
        expect(fn () => DomainHostname::normalize($hostname))
            ->toThrow(InvalidArgumentException::class);
    }
});

it('creates an entitled pending domain with a digest-only challenge', function (): void {
    [$tenant, $owner] = phaseTwoDomainTenant();
    $challenge = app(DomainManagementService::class)->createPendingDomain($tenant, 'Shop.Example.COM.', $owner);
    $domain = $challenge->domain->fresh();

    expect($domain->domain)->toBe('shop.example.com')
        ->and($domain->normalized_domain)->toBe('shop.example.com')
        ->and($domain->status)->toBe(DomainStatus::Pending)
        ->and($domain->verified_at)->toBeNull()
        ->and($domain->verification_token_digest)->not->toBe($challenge->recordValue)
        ->and($domain->verification_token_digest)->toBe(hash('sha256', $challenge->recordValue))
        ->and($domain->verification_record_name)->toBe('_mobile-shop-verification.shop.example.com')
        ->and($domain->verification_expires_at->isFuture())->toBeTrue()
        ->and($domain->verification_attempts)->toBe(0);

    expect(Activity::query()->where('event', 'domain.created')->where('subject_id', $domain->id)->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'domain.verification_initiated')->where('subject_id', $domain->id)->exists())->toBeTrue();
});

it('rejects duplicate hostnames and non-entitled tenants', function (): void {
    [$tenant, $owner] = phaseTwoDomainTenant();
    $service = app(DomainManagementService::class);
    $service->createPendingDomain($tenant, 'duplicate.example.com', $owner);

    expect(fn () => $service->createPendingDomain($tenant, 'DUPLICATE.EXAMPLE.COM.', $owner))
        ->toThrow(InvalidArgumentException::class);

    [$ineligibleTenant, $ineligibleOwner] = phaseTwoDomainTenant(false);

    expect(fn () => $service->createPendingDomain($ineligibleTenant, 'not-entitled.example.com', $ineligibleOwner))
        ->toThrow(DomainException::class);
});

it('regenerates challenges and invalidates the previous challenge', function (): void {
    [$tenant, $owner] = phaseTwoDomainTenant();
    $service = app(DomainManagementService::class);
    $original = $service->createPendingDomain($tenant, 'regenerate.example.com', $owner);
    $regenerated = $service->regenerateVerificationChallenge($original->domain, $owner);

    expect($regenerated->recordValue)->not->toBe($original->recordValue)
        ->and($regenerated->domain->verification_token_digest)->toBe(hash('sha256', $regenerated->recordValue));

    expect(fn () => $service->markVerified($regenerated->domain, $original->recordValue, $owner))
        ->toThrow(DomainException::class);

    $failed = $regenerated->domain->fresh();
    expect($failed->status)->toBe(DomainStatus::Failed)
        ->and($failed->verification_failure_code)->toBe('invalid_challenge')
        ->and(Activity::query()->where('event', 'domain.verification_regenerated')->where('subject_id', $failed->id)->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'domain.verification_failed')->where('subject_id', $failed->id)->exists())->toBeTrue();

    $verified = $service->markVerified($failed, $regenerated->recordValue, $owner);

    expect($verified->status)->toBe(DomainStatus::Verified)
        ->and($verified->verified_at)->not->toBeNull();
});

it('records expired challenge failures', function (): void {
    [$tenant, $owner] = phaseTwoDomainTenant();
    $service = app(DomainManagementService::class);
    $challenge = $service->createPendingDomain($tenant, 'expired.example.com', $owner);
    $challenge->domain->update(['verification_expires_at' => now()->subMinute()]);

    expect(fn () => $service->markVerified($challenge->domain->fresh(), $challenge->recordValue, $owner))
        ->toThrow(DomainException::class);

    $domain = $challenge->domain->fresh();
    expect($domain->status)->toBe(DomainStatus::Failed)
        ->and($domain->verification_failure_code)->toBe('challenge_expired')
        ->and($domain->verification_attempts)->toBe(1);
});

it('requires verification before activation and clears primary on suspension/revocation', function (): void {
    [$tenant, $owner] = phaseTwoDomainTenant();
    $service = app(DomainManagementService::class);
    $challenge = $service->createPendingDomain($tenant, 'lifecycle.example.com', $owner);

    expect(fn () => $service->activate($challenge->domain, $owner))->toThrow(DomainException::class);
    expect($challenge->domain->fresh()->status)->toBe(DomainStatus::Pending);

    $verified = $service->markVerified($challenge->domain, $challenge->recordValue, $owner);
    $active = $service->activate($verified, $owner);
    $tenant = $service->setPrimary($active, $owner);

    expect($tenant->primary_domain_id)->toBe($active->id);

    $suspended = $service->suspend($active, $owner);
    expect($suspended->status)->toBe(DomainStatus::Suspended)
        ->and($tenant->fresh()->primary_domain_id)->toBeNull();

    $activeAgain = $service->activate($suspended, $owner);
    $service->setPrimary($activeAgain, $owner);
    $revoked = $service->revoke($activeAgain, $owner, 'Removed by support.');

    expect($revoked->status)->toBe(DomainStatus::Revoked)
        ->and($tenant->fresh()->primary_domain_id)->toBeNull()
        ->and($revoked->revocation_reason)->toBe('Removed by support.');

    expect(Activity::query()->where('event', 'domain.verified')->where('subject_id', $active->id)->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'domain.activated')->where('subject_id', $active->id)->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'domain.primary_changed')->where('subject_id', $active->id)->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'domain.suspended')->where('subject_id', $active->id)->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'domain.revoked')->where('subject_id', $active->id)->exists())->toBeTrue();
});

it('prevents cross-tenant primary assignment and staff mutation', function (): void {
    [$tenantA, $ownerA] = phaseTwoDomainTenant();
    [$tenantB, $ownerB] = phaseTwoDomainTenant();
    $platformAdmin = User::factory()->create(['is_platform_admin' => true, 'role' => 'owner']);
    $service = app(DomainManagementService::class);

    $challenge = $service->createPendingDomain($tenantB, 'tenant-b.example.com', $ownerB);
    $active = $service->activate(
        $service->markVerified($challenge->domain, $challenge->recordValue, $ownerB),
        $ownerB,
    );

    expect(fn () => $service->setPrimary($active, $ownerA))->toThrow(AuthorizationException::class)
        ->and(fn () => $service->createPendingDomain($tenantA, 'staff.example.com', User::factory()->create([
            'tenant_id' => $tenantA->id,
            'role' => 'staff',
        ])))->toThrow(AuthorizationException::class);

    $platformChallenge = $service->createPendingDomain($tenantA, 'platform.example.com', $platformAdmin);
    expect($platformChallenge->domain->tenant_id)->toBe($tenantA->id);
});

it('does not allow SaaS domain management in Dedicated mode', function (): void {
    [$tenant, $owner] = phaseTwoDomainTenant();
    config()->set([
        'deployment.mode' => DeploymentMode::Dedicated->value,
        'deployment.dedicated.tenant_id' => $tenant->id,
        'deployment.dedicated.canonical_host' => 'dedicated.example.com',
    ]);

    expect(fn () => app(DomainManagementService::class)->createPendingDomain($tenant, 'blocked.example.com', $owner))
        ->toThrow(AuthorizationException::class);
});

it('rolls back domain and activity records when the outer transaction fails', function (): void {
    [$tenant, $owner] = phaseTwoDomainTenant();
    $service = app(DomainManagementService::class);

    expect(fn () => DB::transaction(function () use ($service, $tenant, $owner): void {
        $service->createPendingDomain($tenant, 'rollback.example.com', $owner);
        throw new RuntimeException('rollback test');
    }))->toThrow(RuntimeException::class);

    expect(Domain::query()->where('domain', 'rollback.example.com')->exists())->toBeFalse()
        ->and(Activity::query()->where('event', 'domain.created')->where('properties->domain', 'rollback.example.com')->exists())->toBeFalse();
});
