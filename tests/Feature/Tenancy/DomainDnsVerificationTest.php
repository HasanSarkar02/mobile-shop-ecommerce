<?php

declare(strict_types=1);

use App\Contracts\DnsTxtResolver;
use App\Enums\DeploymentMode;
use App\Enums\DnsTxtLookupStatus;
use App\Enums\DomainStatus;
use App\Jobs\CheckDomainDnsVerification;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\DomainDnsVerificationService;
use App\Services\DomainManagementService;
use App\Support\Dns\DnsTxtLookupResult;
use App\Support\Dns\NativeDnsTxtResolver;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

final class Phase2dFakeDnsTxtResolver implements DnsTxtResolver
{
    public string $hostname = '';

    public string $recordName = '';

    public function __construct(private DnsTxtLookupResult $result) {}

    public function lookup(string $hostname, string $recordName): DnsTxtLookupResult
    {
        $this->hostname = $hostname;
        $this->recordName = $recordName;

        return $this->result;
    }
}

function dnsVerificationTenant(): array
{
    $tenant = Tenant::factory()->create([
        'subdomain' => 'dns-verification-'.Str::lower(Str::random(8)),
        'status' => 'active',
    ]);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    $plan = Plan::query()->create([
        'name' => 'DNS Verification Plan '.Str::random(8),
        'slug' => 'dns-verification-'.Str::lower(Str::random(8)),
        'price' => 1000,
        'billing_period' => 'monthly',
        'custom_domain_allowed' => true,
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

function pendingDnsDomain(): array
{
    [$tenant, $owner] = dnsVerificationTenant();
    $challenge = app(DomainManagementService::class)->createPendingDomain(
        $tenant,
        'dns-'.Str::lower(Str::random(8)).'.example.test',
        $owner,
    );

    return [$challenge->domain, $challenge->recordValue, $tenant, $owner];
}

function bindFakeDnsResult(DnsTxtLookupResult $result): Phase2dFakeDnsTxtResolver
{
    $resolver = new Phase2dFakeDnsTxtResolver($result);
    app()->bind(DnsTxtResolver::class, fn (): Phase2dFakeDnsTxtResolver => $resolver);

    return $resolver;
}

beforeEach(function (): void {
    config()->set([
        'deployment.mode' => DeploymentMode::SaaS->value,
        'domain-verification.max_attempts' => 3,
        'domain-verification.manual_check_cooldown_seconds' => 30,
        'domain-verification.scheduler_cooldown_seconds' => 300,
        'domain-verification.retry_backoff_seconds' => [1, 2, 3],
    ]);
});

it('handles exact, multiple, and split TXT records with the native resolver', function (): void {
    $resolver = new NativeDnsTxtResolver(function (string $name, int $type): array {
        expect($name)->toBe('_mobile-shop-verification.example.test')
            ->and($type)->toBe(DNS_TXT);

        return [
            ['entries' => ['split-', 'value']],
            ['txt' => 'second-value'],
        ];
    });

    $result = $resolver->lookup(
        'Example.TEST.',
        '_mobile-shop-verification.example.test',
    );

    expect($result->status)->toBe(DnsTxtLookupStatus::RecordsFound)
        ->and($result->values)->toBe(['split-value', 'second-value']);
});

it('rejects resolver record names that do not match the normalized hostname', function (): void {
    $resolver = new NativeDnsTxtResolver(fn (): array => [['txt' => 'ignored']]);

    $result = $resolver->lookup('example.test', '_other.example.test');

    expect($result->status)->toBe(DnsTxtLookupStatus::Error)
        ->and($result->errorCode)->toBe('invalid_record_name');
});

it('classifies native resolver failures without leaking warnings', function (): void {
    $resolver = new NativeDnsTxtResolver(fn (): false => false);

    $result = $resolver->lookup('example.test', '_mobile-shop-verification.example.test');

    expect($result->status)->toBe(DnsTxtLookupStatus::TemporaryFailure)
        ->and($result->errorCode)->toBe('temporary_failure');
});

it('verifies the raw DNS TXT value by hashing it against the current digest', function (): void {
    [$domain, $challenge] = pendingDnsDomain();
    $resolver = bindFakeDnsResult(DnsTxtLookupResult::records([$challenge]));

    $result = app(DomainDnsVerificationService::class)->check($domain->id, $domain->verification_token_digest);
    $verified = $domain->fresh();

    expect($result->verified)->toBeTrue()
        ->and($resolver->hostname)->toBe($domain->domain)
        ->and($resolver->recordName)->toBe($domain->verification_record_name)
        ->and($verified->status)->toBe(DomainStatus::Verified)
        ->and($verified->verified_at)->not->toBeNull();
});

it('records wrong, missing, NXDOMAIN, SERVFAIL, and timeout outcomes', function (): void {
    $cases = [
        [DnsTxtLookupResult::records(['wrong-value']), 'wrong_txt', false, DomainStatus::Failed],
        [DnsTxtLookupResult::missing(), 'missing_txt', true, DomainStatus::Pending],
        [DnsTxtLookupResult::failure(DnsTxtLookupStatus::NxDomain, 'nxdomain', 'No such name.'), 'nxdomain', false, DomainStatus::Failed],
        [DnsTxtLookupResult::failure(DnsTxtLookupStatus::ServFail, 'servfail', 'Temporary DNS failure.'), 'servfail', true, DomainStatus::Pending],
        [DnsTxtLookupResult::failure(DnsTxtLookupStatus::Error, 'timeout', 'DNS timeout.'), 'timeout', true, DomainStatus::Pending],
    ];

    foreach ($cases as [$lookup, $failureCode, $retryable, $status]) {
        [$domain, $challenge] = pendingDnsDomain();
        bindFakeDnsResult($lookup);

        $result = app(DomainDnsVerificationService::class)->check($domain->id, $domain->verification_token_digest);
        $updated = $domain->fresh();

        expect($result->failureCode)->toBe($failureCode)
            ->and($result->retryable)->toBe($retryable)
            ->and($updated->status)->toBe($status)
            ->and($updated->verification_attempts)->toBe(1);
    }
});

it('rejects expired challenges and stops retrying at the configured attempt limit', function (): void {
    [$domain, $challenge] = pendingDnsDomain();
    $domain->update(['verification_expires_at' => now()->subMinute()]);
    bindFakeDnsResult(DnsTxtLookupResult::records([$challenge]));

    $expired = app(DomainDnsVerificationService::class)->check($domain->id, $domain->verification_token_digest);

    expect($expired->failureCode)->toBe('challenge_expired')
        ->and($domain->fresh()->status)->toBe(DomainStatus::Failed);

    [$limitedDomain, $limitedChallenge] = pendingDnsDomain();
    $limitedDomain->update(['verification_attempts' => 2]);
    bindFakeDnsResult(DnsTxtLookupResult::missing());

    $limited = app(DomainDnsVerificationService::class)->check($limitedDomain->id, $limitedDomain->verification_token_digest);

    expect($limited->retryable)->toBeFalse()
        ->and($limitedDomain->fresh()->status)->toBe(DomainStatus::Failed);
});

it('cannot verify a stale challenge after regeneration', function (): void {
    [$domain, $oldChallenge, $tenant, $owner] = pendingDnsDomain();
    $newChallenge = app(DomainManagementService::class)->regenerateVerificationChallenge($domain, $owner);
    bindFakeDnsResult(DnsTxtLookupResult::records([$oldChallenge]));

    $result = app(DomainDnsVerificationService::class)->check($domain->id, hash('sha256', $oldChallenge));

    expect($result->failureCode)->toBe('stale_challenge')
        ->and($domain->fresh()->status)->toBe(DomainStatus::Pending)
        ->and($domain->fresh()->verification_token_digest)->toBe(hash('sha256', $newChallenge->recordValue));
});

it('dispatches one unique check per domain and current digest', function (): void {
    [$domain] = pendingDnsDomain();
    Queue::fake();
    $verification = app(DomainDnsVerificationService::class);

    expect($verification->dispatchCheck($domain))->toBeTrue()
        ->and($verification->dispatchCheck($domain))->toBeFalse();

    Queue::assertPushed(CheckDomainDnsVerification::class, 1);
    Queue::assertPushed(CheckDomainDnsVerification::class, fn (CheckDomainDnsVerification $job): bool => $job->domainId === $domain->id
        && $job->expectedDigest === $domain->verification_token_digest
    );
});

it('does not dispatch checks in Dedicated mode', function (): void {
    [$domain] = pendingDnsDomain();
    config()->set('deployment.mode', DeploymentMode::Dedicated->value);
    Queue::fake();

    expect(app(DomainDnsVerificationService::class)->dispatchCheck($domain))->toBeFalse();
    Queue::assertNothingPushed();
});

it('dispatches only due pending domains from the scheduler command', function (): void {
    [$dueDomain] = pendingDnsDomain();
    [$futureDomain] = pendingDnsDomain();
    $futureDomain->update(['last_checked_at' => now()]);
    [$expiredDomain] = pendingDnsDomain();
    $expiredDomain->update(['verification_expires_at' => now()->subMinute()]);
    Queue::fake();

    Artisan::call('domains:dispatch-dns-checks');

    Queue::assertPushed(CheckDomainDnsVerification::class, 1);
    Queue::assertPushed(CheckDomainDnsVerification::class, fn (CheckDomainDnsVerification $job): bool => $job->domainId === $dueDomain->id);
});
