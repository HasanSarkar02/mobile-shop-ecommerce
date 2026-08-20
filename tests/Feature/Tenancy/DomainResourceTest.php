<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Enums\DomainStatus;
use App\Filament\Platform\Resources\DomainResource;
use App\Filament\Platform\Resources\DomainResource\Pages\CreateDomain;
use App\Filament\Platform\Resources\DomainResource\Pages\ListDomains;
use App\Filament\Platform\Resources\DomainResource\Pages\ViewDomain;
use App\Jobs\CheckDomainDnsVerification;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\DomainManagementService;
use Filament\Facades\Filament;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

function platformDomainTenantForUi(): array
{
    $tenant = Tenant::factory()->create([
        'subdomain' => 'platform-domain-'.fake()->unique()->numberBetween(1000, 9999),
        'status' => 'active',
    ]);
    $plan = Plan::query()->create([
        'name' => 'Platform Domain UI Plan '.fake()->unique()->numberBetween(1000, 9999),
        'slug' => 'platform-domain-ui-'.fake()->unique()->numberBetween(1000, 9999),
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

    return [$tenant, User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret'])];
}

beforeEach(function (): void {
    config()->set('deployment.mode', DeploymentMode::SaaS->value);
    Filament::setCurrentPanel('platform');
});

it('registers the Add Domain header action on the domains list page', function (): void {
    $method = new ReflectionMethod(ListDomains::class, 'getHeaderActions');
    $method->setAccessible(true);
    $actions = $method->invoke(new ListDomains);

    expect($actions)->toHaveCount(1)
        ->and($actions[0]->getName())->toBe('create')
        ->and($actions[0]->getLabel())->toBe('Add Domain');
});

it('renders the Add Domain header action for an authorized platform admin', function (): void {
    [, $platformAdmin] = platformDomainTenantForUi();
    Auth::guard('platform')->login($platformAdmin);

    Livewire::test(ListDomains::class)
        ->assertActionExists('create')
        ->assertActionHasLabel('create', 'Add Domain');

    Auth::guard('platform')->logout();
});

it('resolves the Add Domain action to the create page route', function (): void {
    expect(CreateDomain::getUrl())->toBe(route('filament.platform.resources.domains.create'));
});

it('allows only SaaS Platform Admins to access DomainResource', function (): void {
    $platformAdmin = User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);
    $staff = User::factory()->create(['is_platform_admin' => false, 'role' => 'staff']);
    $domain = Domain::query()->create([
        'tenant_id' => Tenant::factory()->create()->id,
        'domain' => 'access.example.com',
        'normalized_domain' => 'access.example.com',
        'status' => 'pending',
    ]);

    Auth::guard('platform')->login($platformAdmin);
    expect(DomainResource::canViewAny())->toBeTrue()
        ->and(DomainResource::canCreate())->toBeTrue()
        ->and(DomainResource::canView($domain))->toBeTrue();

    Auth::guard('platform')->login($staff);
    expect(DomainResource::canViewAny())->toBeFalse()
        ->and(DomainResource::canCreate())->toBeFalse()
        ->and(DomainResource::canView($domain))->toBeFalse();

    config()->set('deployment.mode', DeploymentMode::Dedicated->value);
    Auth::guard('platform')->login($platformAdmin);
    expect(DomainResource::canViewAny())->toBeFalse()
        ->and(DomainResource::canCreate())->toBeFalse();

    Auth::guard('platform')->logout();
});

it('creates a pending domain through the DomainManagementService contract', function (): void {
    [$tenant, $platformAdmin] = platformDomainTenantForUi();
    Auth::guard('platform')->login($platformAdmin);

    Livewire::test(CreateDomain::class)
        ->fillForm([
            'tenant_id' => $tenant->id,
            'domain' => 'Platform-Create.Example.COM.',
        ])
        ->call('create')
        ->assertHasNoErrors();

    $domain = Domain::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $challengeValue = session()->get(DomainResource::challengeSessionKey($domain->id))['record_value'] ?? null;

    expect($domain->domain)->toBe('platform-create.example.com')
        ->and($domain->status->value)->toBe('pending')
        ->and($domain->verification_token_digest)->not->toBeNull()
        ->and($challengeValue)->toBeString()
        ->and(DomainResource::challengeSessionData($domain)['record_value'] ?? null)->toBe($challengeValue);

    Livewire::test(ViewDomain::class, ['record' => $domain->getRouteKey()])
        ->assertSee($challengeValue);

    Auth::guard('platform')->logout();
});

it('keeps the current TXT value visible across view and Check DNS Now requests', function (): void {
    [$tenant, $platformAdmin] = platformDomainTenantForUi();
    Auth::guard('platform')->login($platformAdmin);
    Queue::fake();

    $challenge = app(DomainManagementService::class)->createPendingDomain(
        $tenant,
        'persistent-challenge.example.com',
        $platformAdmin,
    );
    ViewDomain::rememberChallenge($challenge);
    $domain = $challenge->domain->fresh();
    $digest = $domain->verification_token_digest;

    Livewire::test(ViewDomain::class, ['record' => $domain->getRouteKey()])
        ->assertSee($challenge->recordValue);

    Livewire::test(ViewDomain::class, ['record' => $domain->getRouteKey()])
        ->callAction('checkDns')
        ->assertSee($challenge->recordValue);

    Livewire::test(ViewDomain::class, ['record' => $domain->getRouteKey()])
        ->assertSee($challenge->recordValue);

    Queue::assertPushed(CheckDomainDnsVerification::class, fn (CheckDomainDnsVerification $job): bool => $job->domainId === $domain->id
        && $job->expectedDigest === $digest
    );

    expect(DomainResource::challengeSessionData($domain)['record_value'] ?? null)->toBe($challenge->recordValue)
        ->and($domain->fresh()->verification_token_digest)->toBe($digest)
        ->and($domain->fresh()->verification_token_digest)->not->toBe($challenge->recordValue);

    Auth::guard('platform')->logout();
});

it('only exposes a session challenge for a valid pending or failed domain', function (): void {
    [$tenant, $platformAdmin] = platformDomainTenantForUi();
    $challenge = app(DomainManagementService::class)->createPendingDomain(
        $tenant,
        'challenge-validity.example.com',
        $platformAdmin,
    );
    $domain = $challenge->domain->fresh();
    $sessionData = [
        'domain_id' => $domain->id,
        'record_name' => $challenge->recordName,
        'record_value' => $challenge->recordValue,
        'expires_at' => $challenge->expiresAt->toDateTimeString(),
    ];
    session()->put(DomainResource::challengeSessionKey($domain->id), $sessionData);

    session()->put(DomainResource::challengeSessionKey($domain->id), [...$sessionData, 'domain_id' => $domain->id + 1]);

    expect(DomainResource::challengeSessionData($domain))->toBe([]);

    session()->put(DomainResource::challengeSessionKey($domain->id), $sessionData);

    expect(DomainResource::challengeSessionData($domain)['record_value'] ?? null)->toBe($challenge->recordValue);

    $domain->update(['status' => DomainStatus::Failed]);

    expect(DomainResource::challengeSessionData($domain->fresh())['record_value'] ?? null)->toBe($challenge->recordValue);

    foreach ([DomainStatus::Verified, DomainStatus::Active, DomainStatus::Suspended, DomainStatus::Revoked] as $status) {
        $domain->update(['status' => $status]);

        expect(DomainResource::challengeSessionData($domain->fresh()))->toBe([]);
    }

    $domain->update([
        'status' => DomainStatus::Pending,
        'verification_expires_at' => now()->subMinute(),
    ]);

    expect(DomainResource::challengeSessionData($domain->fresh()))->toBe([]);

    session()->forget(DomainResource::challengeSessionKey($domain->id));

    expect(DomainResource::challengeSessionData($domain->fresh()))->toBe([]);
});

it('rejects mismatched and regenerated session challenge values', function (): void {
    [$tenant, $platformAdmin] = platformDomainTenantForUi();
    $oldChallenge = app(DomainManagementService::class)->createPendingDomain(
        $tenant,
        'regenerated-challenge.example.com',
        $platformAdmin,
    );
    $domain = $oldChallenge->domain->fresh();

    session()->put(DomainResource::challengeSessionKey($domain->id), [
        'domain_id' => $domain->id,
        'record_name' => $oldChallenge->recordName,
        'record_value' => $oldChallenge->recordValue,
        'expires_at' => $oldChallenge->expiresAt->toDateTimeString(),
    ]);

    $domain->update(['verification_token_digest' => hash('sha256', 'different-value')]);

    expect(DomainResource::challengeSessionData($domain->fresh()))->toBe([]);

    $domain->update(['verification_token_digest' => hash('sha256', $oldChallenge->recordValue)]);
    $newChallenge = app(DomainManagementService::class)->regenerateVerificationChallenge($domain, $platformAdmin);

    expect(DomainResource::challengeSessionData($domain->fresh()))->toBe([]);

    ViewDomain::rememberChallenge($newChallenge);

    expect(DomainResource::challengeSessionData($domain->fresh())['record_value'] ?? null)
        ->toBe($newChallenge->recordValue)
        ->not->toBe($oldChallenge->recordValue);
});

it('does not leak raw TXT challenges to domain digests, activity, or application logs', function (): void {
    [$tenant, $platformAdmin] = platformDomainTenantForUi();
    $messages = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$messages): void {
        $messages[] = $event->message;
    });

    $challenge = app(DomainManagementService::class)->createPendingDomain(
        $tenant,
        'challenge-no-leak.example.com',
        $platformAdmin,
    );
    $domain = $challenge->domain->fresh();
    $activities = Activity::query()->where('subject_id', $domain->id)->get();

    expect($domain->verification_token_digest)->not->toBe($challenge->recordValue)
        ->and($activities->toJson())->not->toContain($challenge->recordValue)
        ->and(json_encode($messages))->not->toContain($challenge->recordValue);
});

it('renders the domain view page header actions for enum-cast statuses', function (): void {
    [$tenant, $platformAdmin] = platformDomainTenantForUi();
    Auth::guard('platform')->login($platformAdmin);

    $domain = Domain::query()->create([
        'tenant_id' => $tenant->id,
        'domain' => 'view-page.example.com',
        'normalized_domain' => 'view-page.example.com',
        'status' => 'active',
    ]);

    Livewire::test(ViewDomain::class, ['record' => $domain->getRouteKey()])
        ->assertActionExists('activate')
        ->assertActionExists('setPrimary')
        ->assertActionExists('suspend')
        ->assertActionExists('revoke')
        ->assertActionHidden('checkDns')
        ->assertActionHidden('removePending');

    expect($domain->getAttribute('status'))->toBeInstanceOf(DomainStatus::class)
        ->and($domain->status)->toBe(DomainStatus::Active);

    Auth::guard('platform')->logout();
});

it('shows only the current tenant domains through the Tenant relationship', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    Domain::query()->create([
        'tenant_id' => $tenantA->id,
        'domain' => 'tenant-a.example.com',
        'normalized_domain' => 'tenant-a.example.com',
        'status' => 'pending',
    ]);
    Domain::query()->create([
        'tenant_id' => $tenantB->id,
        'domain' => 'tenant-b.example.com',
        'normalized_domain' => 'tenant-b.example.com',
        'status' => 'pending',
    ]);

    expect($tenantA->domains()->pluck('domain')->all())->toBe(['tenant-a.example.com'])
        ->and($tenantB->domains()->pluck('domain')->all())->toBe(['tenant-b.example.com']);
});
