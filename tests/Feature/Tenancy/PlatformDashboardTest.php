<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Enums\DomainStatus;
use App\Enums\PlanChangeRequestStatus;
use App\Enums\SubscriptionChargeStatus;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionPaymentIntent;
use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Filament\Platform\Pages\PlatformDashboard;
use App\Filament\Platform\Resources\DomainResource;
use App\Filament\Platform\Resources\PlanChangeRequestResource;
use App\Filament\Platform\Resources\PlanChangeRequestResource\Pages\ListPlanChangeRequests;
use App\Filament\Platform\Resources\PlatformAdminResource;
use App\Filament\Platform\Resources\SubscriptionChargeResource;
use App\Filament\Platform\Resources\SubscriptionChargeResource\Pages\ListSubscriptionCharges;
use App\Filament\Platform\Resources\SubscriptionPaymentResource;
use App\Filament\Platform\Resources\TenantResource;
use App\Filament\Platform\Resources\TenantSubscriptionResource;
use App\Filament\Platform\Resources\TenantSubscriptionResource\Pages\ListTenantSubscriptions;
use App\Filament\Platform\Widgets\PlatformStatsOverview;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\SchedulerHeartbeat;
use App\Models\SubscriptionCharge;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\PlatformDashboardService;
use App\Services\PlatformRecentActivityService;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('deployment.mode', DeploymentMode::SaaS->value);
    Filament::setCurrentPanel('platform');
    app(Tenancy::class)->set(null);
    Cache::flush();
    seedBootstrapPlans();
});

function d1Admin(): User
{
    return User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);
}

function d1Tenant(string $status = 'active'): Tenant
{
    return Tenant::factory()->create([
        'subdomain' => 'd1-'.Str::lower(Str::random(8)),
        'status' => $status,
    ]);
}

function d1Subscription(Tenant $tenant, string $status = 'active', ?CarbonInterface $periodEndsAt = null): TenantSubscription
{
    return TenantSubscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => Plan::query()->where('slug', 'starter')->firstOrFail()->id,
        'status' => SubscriptionStatus::from($status),
        'current_period_starts_at' => now()->subMonth(),
        'current_period_ends_at' => $periodEndsAt ?? now()->addMonth(),
    ]);
}

function d1PlanRequest(Tenant $tenant, string $status = 'pending'): PlanChangeRequest
{
    $tenancy = app(Tenancy::class);
    $tenancy->set($tenant);

    try {
        return PlanChangeRequest::query()->create([
            'requested_plan_id' => Plan::query()->where('slug', 'growth')->firstOrFail()->id,
            'status' => PlanChangeRequestStatus::from($status),
        ]);
    } finally {
        $tenancy->set(null);
    }
}

function d1Charge(Tenant $tenant, int $net, int $paid = 0, string $status = 'open'): SubscriptionCharge
{
    return SubscriptionCharge::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => Plan::query()->where('slug', 'starter')->firstOrFail()->id,
        'intent' => SubscriptionPaymentIntent::AssignPlan,
        'period_starts_at' => now()->subMonth(),
        'period_ends_at' => now()->addMonth(),
        'base_amount' => $net,
        'discount_amount' => 0,
        'net_amount' => $net,
        'paid_amount' => $paid,
        'status' => SubscriptionChargeStatus::from($status),
    ]);
}

function d1Domain(Tenant $tenant, string $hostname, string $status): Domain
{
    return Domain::query()->create([
        'tenant_id' => $tenant->id,
        'domain' => $hostname,
        'normalized_domain' => $hostname,
        'status' => DomainStatus::from($status),
    ]);
}

it('lets an active platform admin view the dashboard', function (): void {
    Auth::guard('platform')->login(d1Admin());

    Livewire::test(PlatformDashboard::class)
        ->assertSuccessful()
        ->assertSee('Quick Links');
});

it('denies a non-platform user the dashboard', function (): void {
    $tenant = d1Tenant();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner', 'is_platform_admin' => false]);
    Auth::guard('platform')->login($owner);

    Livewire::test(PlatformDashboard::class)->assertForbidden();
});

it('denies an inactive platform admin the dashboard', function (): void {
    $inactive = User::factory()->create(['is_platform_admin' => true, 'is_active' => false]);
    Auth::guard('platform')->login($inactive);

    Livewire::test(PlatformDashboard::class)->assertForbidden();
});

it('denies the dashboard in Dedicated mode', function (): void {
    config()->set('deployment.mode', DeploymentMode::Dedicated->value);
    Auth::guard('platform')->login(d1Admin());

    Livewire::test(PlatformDashboard::class)->assertForbidden();
});

it('reports correct KPI counts', function (): void {
    $activeTenant = d1Tenant('active');
    $trialTenant = d1Tenant('trial');
    d1Subscription($activeTenant, 'active', now()->addDays(3));
    d1Subscription($trialTenant, 'trialing', now()->addDays(10));
    d1PlanRequest($activeTenant);
    d1PlanRequest($trialTenant, 'approved');
    d1Charge($activeTenant, 1000);
    d1Charge($trialTenant, 2000, 500, 'partially_paid');
    d1Charge($activeTenant, 9999, 9999, 'paid');

    Domain::query()->create([
        'tenant_id' => $activeTenant->id,
        'domain' => 'shop-a.test',
        'normalized_domain' => 'shop-a.test',
        'status' => DomainStatus::Active,
    ]);
    Domain::query()->create([
        'tenant_id' => $trialTenant->id,
        'domain' => 'shop-b.test',
        'normalized_domain' => 'shop-b.test',
        'status' => DomainStatus::Pending,
    ]);
    Domain::query()->create([
        'tenant_id' => $trialTenant->id,
        'domain' => 'shop-c.test',
        'normalized_domain' => 'shop-c.test',
        'status' => DomainStatus::Failed,
    ]);

    SubscriptionPayment::query()->create([
        'tenant_id' => $activeTenant->id,
        'plan_id' => Plan::query()->where('slug', 'starter')->firstOrFail()->id,
        'intent' => SubscriptionPaymentIntent::AssignPlan,
        'status' => SubscriptionPaymentStatus::Pending,
        'amount' => 1000,
    ]);

    $kpis = app(PlatformDashboardService::class)->kpis();

    expect($kpis['total_tenants'])->toBe(2)
        ->and($kpis['active_tenants'])->toBe(1)
        ->and($kpis['trial_tenants'])->toBe(1)
        ->and($kpis['expiring_subscriptions'])->toBe(1)
        ->and($kpis['pending_plan_change_requests'])->toBe(1)
        ->and($kpis['pending_subscription_payments'])->toBe(1)
        ->and($kpis['outstanding_charges'])->toBe(2)
        ->and($kpis['outstanding_amount'])->toBe(2500)
        ->and($kpis['active_domains'])->toBe(1)
        ->and($kpis['dns_pending'])->toBe(1)
        ->and($kpis['dns_failed'])->toBe(1);
});

it('displays the outstanding charge amount formatted in taka', function (): void {
    d1Tenant('active');
    d1Charge(d1Tenant('active'), 123456);

    Livewire::test(PlatformStatsOverview::class)
        ->assertSee('৳1,234.56')
        ->assertDontSee('123456');
});

it('renders a zero state when there is no data', function (): void {
    Auth::guard('platform')->login(d1Admin());

    Livewire::test(PlatformDashboard::class)->assertSuccessful();

    Livewire::test(PlatformStatsOverview::class)
        ->assertSee('Total Tenants')
        ->assertSee('৳0.00')
        ->assertSee('DNS Failed');
});

it('provides quick links to every platform resource', function (): void {
    Auth::guard('platform')->login(d1Admin());

    Livewire::test(PlatformDashboard::class)
        ->assertSee(TenantResource::getUrl('index'))
        ->assertSee(TenantSubscriptionResource::getUrl('index'))
        ->assertSee(SubscriptionChargeResource::getUrl('index'))
        ->assertSee(SubscriptionPaymentResource::getUrl('index'))
        ->assertSee(PlanChangeRequestResource::getUrl('index'))
        ->assertSee(DomainResource::getUrl('index'))
        ->assertSee(PlatformAdminResource::getUrl('index'));
});

it('keeps KPI computation to a bounded number of queries and caches it', function (): void {
    d1Tenant('active');
    d1Tenant('trial');

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    app(PlatformDashboardService::class)->kpis();
    $firstPass = $queries;

    $queries = 0;
    app(PlatformDashboardService::class)->kpis();
    $secondPass = $queries;

    expect($firstPass)->toBeLessThanOrEqual(12)
        ->and($secondPass)->toBe(0);
});

it('reports operational alert counts fresh from current data', function (): void {
    $activeTenant = d1Tenant('active');
    $trialTenant = d1Tenant('trial');
    $thirdTenant = d1Tenant('trial');
    d1Subscription($activeTenant, 'active', now()->addDays(3));
    d1Subscription($trialTenant, 'active', now()->addMonth());
    d1Subscription($thirdTenant, 'trialing', now()->addDays(20));
    d1PlanRequest($activeTenant);
    d1PlanRequest($trialTenant, 'approved');
    d1Charge($activeTenant, 1000);
    d1Charge($trialTenant, 2000, 500, 'partially_paid');
    d1Charge($activeTenant, 9999, 9999, 'paid');
    d1Charge($trialTenant, 5000, 5000, 'paid');

    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();

    SubscriptionPayment::query()->create([
        'tenant_id' => $activeTenant->id,
        'plan_id' => $starter->id,
        'intent' => SubscriptionPaymentIntent::AssignPlan,
        'status' => SubscriptionPaymentStatus::Pending,
        'amount' => 1000,
    ]);
    SubscriptionPayment::query()->create([
        'tenant_id' => $trialTenant->id,
        'plan_id' => $starter->id,
        'intent' => SubscriptionPaymentIntent::AssignPlan,
        'status' => SubscriptionPaymentStatus::Rejected,
        'amount' => 500,
        'rejected_at' => now()->subDays(2),
    ]);
    SubscriptionPayment::query()->create([
        'tenant_id' => $trialTenant->id,
        'plan_id' => $starter->id,
        'intent' => SubscriptionPaymentIntent::AssignPlan,
        'status' => SubscriptionPaymentStatus::Rejected,
        'amount' => 500,
        'rejected_at' => now()->subDays(30),
    ]);

    $alerts = app(PlatformDashboardService::class)->operationalAlerts();

    expect($alerts)->toBe([
        ['key' => 'expiring_subscriptions', 'count' => 1],
        ['key' => 'pending_plan_change_requests', 'count' => 1],
        ['key' => 'pending_subscription_payments', 'count' => 1],
        ['key' => 'outstanding_subscription_charges', 'count' => 2, 'amount' => 2500],
        ['key' => 'rejected_subscription_payments', 'count' => 1],
    ]);
});

it('renders operational alerts with actionable filtered links', function (): void {
    Auth::guard('platform')->login(d1Admin());

    $tenant = d1Tenant('active');
    d1Subscription($tenant, 'active', now()->addDays(3));
    d1PlanRequest($tenant);
    d1Charge($tenant, 100000);

    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    SubscriptionPayment::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $starter->id,
        'intent' => SubscriptionPaymentIntent::AssignPlan,
        'status' => SubscriptionPaymentStatus::Pending,
        'amount' => 1000,
    ]);
    SubscriptionPayment::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $starter->id,
        'intent' => SubscriptionPaymentIntent::AssignPlan,
        'status' => SubscriptionPaymentStatus::Rejected,
        'amount' => 500,
        'rejected_at' => now()->subDays(1),
    ]);

    $expiringUrl = TenantSubscriptionResource::getUrl('index', ['tableFilters[expiring][value]' => 'within_7_days']);
    $pendingRequestsUrl = PlanChangeRequestResource::getUrl('index', ['tableFilters[status][value]' => 'pending']);
    $pendingPaymentsUrl = SubscriptionPaymentResource::getUrl('index', ['tableFilters[status][value]' => 'pending']);
    $outstandingUrl = SubscriptionChargeResource::getUrl('index', ['tableFilters[outstanding][value]' => 'open_or_partially_paid']);
    $rejectedUrl = SubscriptionPaymentResource::getUrl('index', ['tableFilters[status][value]' => 'rejected']);

    Livewire::test(PlatformDashboard::class)
        ->assertSuccessful()
        ->assertSee('Subscriptions expiring within 7 days')
        ->assertSee('Pending plan change requests')
        ->assertSee('Pending subscription payments')
        ->assertSee('Outstanding subscription charges')
        ->assertSee('Recently rejected subscription payments')
        ->assertSee('৳1,000.00')
        ->assertSee($expiringUrl)
        ->assertSee($pendingRequestsUrl)
        ->assertSee($pendingPaymentsUrl)
        ->assertSee($outstandingUrl)
        ->assertSee($rejectedUrl);
});

it('renders an up-to-date message when there are no alerts', function (): void {
    Auth::guard('platform')->login(d1Admin());
    d1Tenant('active');

    Livewire::test(PlatformDashboard::class)
        ->assertSuccessful()
        ->assertSee('All subscription and payment operations are up to date.')
        ->assertDontSee('Subscriptions expiring within 7 days');
});

it('applies the expiring subscription filter', function (): void {
    Auth::guard('platform')->login(d1Admin());
    $expiringTenant = d1Tenant('active');
    $laterTenant = d1Tenant('active');
    $expiring = d1Subscription($expiringTenant, 'active', now()->addDays(3));
    $later = d1Subscription($laterTenant, 'active', now()->addMonth());

    Livewire::test(ListTenantSubscriptions::class)
        ->filterTable('expiring', 'within_7_days')
        ->assertCanSeeTableRecords([$expiring])
        ->assertCanNotSeeTableRecords([$later]);
});

it('applies the pending plan change request status filter', function (): void {
    Auth::guard('platform')->login(d1Admin());
    $tenant = d1Tenant('active');
    $pending = d1PlanRequest($tenant);
    $approved = d1PlanRequest($tenant, 'approved');

    Livewire::test(ListPlanChangeRequests::class)
        ->filterTable('status', 'pending')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$approved]);
});

it('applies the outstanding charges filter', function (): void {
    Auth::guard('platform')->login(d1Admin());
    $tenant = d1Tenant('active');
    $open = d1Charge($tenant, 1000);
    $partiallyPaid = d1Charge($tenant, 2000, 500, 'partially_paid');
    $paid = d1Charge($tenant, 9999, 9999, 'paid');

    Livewire::test(ListSubscriptionCharges::class)
        ->filterTable('outstanding', 'open_or_partially_paid')
        ->assertCanSeeTableRecords([$open, $partiallyPaid])
        ->assertCanNotSeeTableRecords([$paid]);
});

it('denies a non-platform user the operational alerts', function (): void {
    $tenant = d1Tenant();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner', 'is_platform_admin' => false]);
    Auth::guard('platform')->login($owner);

    Livewire::test(PlatformDashboard::class)->assertForbidden();
});

it('denies the operational alerts in Dedicated mode', function (): void {
    config()->set('deployment.mode', DeploymentMode::Dedicated->value);
    Auth::guard('platform')->login(d1Admin());

    Livewire::test(PlatformDashboard::class)->assertForbidden();
});

it('keeps operational alerts to a bounded number of queries', function (): void {
    $tenant = d1Tenant('active');
    d1Subscription($tenant, 'active', now()->addDays(3));
    d1PlanRequest($tenant);
    d1Charge($tenant, 1000);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    app(PlatformDashboardService::class)->operationalAlerts();

    expect($queries)->toBeLessThanOrEqual(8);
});

it('reports DNS health alert counts fresh from current data', function (): void {
    $activeTenant = d1Tenant('active');
    $trialTenant = d1Tenant('trial');
    d1Domain($activeTenant, 'd3-failed.test', 'failed');
    d1Domain($trialTenant, 'd3-pending.test', 'pending');
    d1Domain($activeTenant, 'd3-healthy.test', 'active');
    d1Domain($trialTenant, 'd3-verified.test', 'verified');

    $alerts = app(PlatformDashboardService::class)->dnsHealthAlerts();
    $pending = collect($alerts)->firstWhere('key', 'pending_domains');
    $failed = collect($alerts)->firstWhere('key', 'failed_domains');

    expect($pending['count'])->toBe(1)
        ->and($failed['count'])->toBe(1)
        ->and($failed['domains'])->toHaveCount(1)
        ->and($failed['domains'][0]['domain'])->toBe('d3-failed.test')
        ->and($failed['domains'][0]['tenant'])->toBe((string) $activeTenant->name);
});

it('renders DNS health alerts with actionable filtered links', function (): void {
    Auth::guard('platform')->login(d1Admin());

    $tenant = d1Tenant('active');
    d1Domain($tenant, 'd3-failed.test', 'failed');
    d1Domain($tenant, 'd3-pending.test', 'pending');

    $failedUrl = DomainResource::getUrl('index', ['tableFilters[status][value]' => 'failed']);
    $pendingUrl = DomainResource::getUrl('index', ['tableFilters[status][value]' => 'pending']);

    Livewire::test(PlatformDashboard::class)
        ->assertSuccessful()
        ->assertSee('Pending verification')
        ->assertSee('Failed verification')
        ->assertSee('View Pending')
        ->assertSee('View Failed')
        ->assertSee($failedUrl)
        ->assertSee($pendingUrl);
});

it('renders failed domain operational details safely', function (): void {
    Auth::guard('platform')->login(d1Admin());

    $tenant = Tenant::factory()->create([
        'name' => 'Acme Shop',
        'subdomain' => 'd3-'.Str::lower(Str::random(8)),
        'status' => 'active',
    ]);

    $digest = hash('sha256', 'super-secret-txt-value');

    Domain::query()->create([
        'tenant_id' => $tenant->id,
        'domain' => 'd3-failed.test',
        'normalized_domain' => 'd3-failed.test',
        'status' => DomainStatus::Failed,
        'verification_token_digest' => $digest,
        'verification_failure_code' => 'TXT_RECORD_MISMATCH',
        'verification_failure_message' => 'The TXT record value does not match the expected challenge.',
        'verification_attempts' => 3,
        'last_checked_at' => now()->subHour(),
    ]);

    Livewire::test(PlatformDashboard::class)
        ->assertSuccessful()
        ->assertSee('d3-failed.test')
        ->assertSee('Acme Shop')
        ->assertSee('TXT_RECORD_MISMATCH')
        ->assertSee('The TXT record value does not match the expected challenge.')
        ->assertSee('3 attempt(s)')
        ->assertDontSee($digest)
        ->assertDontSee('super-secret-txt-value');
});

it('renders a healthy message when there are no DNS alerts', function (): void {
    Auth::guard('platform')->login(d1Admin());

    $tenant = d1Tenant('active');
    d1Domain($tenant, 'd3-healthy.test', 'active');
    d1Domain($tenant, 'd3-verified.test', 'verified');

    Livewire::test(PlatformDashboard::class)
        ->assertSuccessful()
        ->assertSee('All domains are verified and healthy.')
        ->assertDontSee('Failed verification');
});

it('denies a non-platform user the DNS alerts', function (): void {
    $tenant = d1Tenant();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner', 'is_platform_admin' => false]);
    Auth::guard('platform')->login($owner);

    Livewire::test(PlatformDashboard::class)->assertForbidden();
});

it('denies the DNS alerts in Dedicated mode', function (): void {
    config()->set('deployment.mode', DeploymentMode::Dedicated->value);
    Auth::guard('platform')->login(d1Admin());

    Livewire::test(PlatformDashboard::class)->assertForbidden();
});

it('keeps DNS health alerts to a bounded number of queries', function (): void {
    $tenant = d1Tenant('active');
    d1Domain($tenant, 'd3-failed.test', 'failed');
    d1Domain($tenant, 'd3-pending.test', 'pending');

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    app(PlatformDashboardService::class)->dnsHealthAlerts();

    expect($queries)->toBeLessThanOrEqual(5);
});

it('reports the failed jobs count', function (): void {
    DB::table('failed_jobs')->insert([
        ['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'RuntimeException: one', 'failed_at' => now()->subHour()],
        ['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'emails', 'payload' => '{}', 'exception' => 'RuntimeException: two', 'failed_at' => now()],
    ]);

    $health = app(PlatformDashboardService::class)->systemHealth();

    expect($health['queue']['failed_jobs_count'])->toBe(2);
});

it('exposes only safe fields for recent failed jobs', function (): void {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'LeakyJob', 'data' => ['secret' => 'hunter2']]),
        'exception' => "RuntimeException: Something broke\n\n#0 /app/worker.php(12): handle()\n#1 {main}",
        'failed_at' => now()->subHour(),
    ]);

    $recent = app(PlatformDashboardService::class)->systemHealth()['queue']['recent_failed_jobs'];

    expect($recent)->toHaveCount(1)
        ->and($recent[0]['queue'])->toBe('default')
        ->and($recent[0]['failed_at'])->toBe(now()->subHour()->toDateTimeString())
        ->and($recent[0]['exception'])->toBe('RuntimeException: Something broke')
        ->and($recent[0]['exception'])->not->toContain('#0')
        ->and($recent[0])->not->toHaveKey('payload');
});

it('never renders failed job payloads or full stack traces', function (): void {
    Auth::guard('platform')->login(d1Admin());

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'LeakyJob', 'data' => ['password' => 'hunter2']]),
        'exception' => "RuntimeException: boom\n\n#0 /app/worker.php(12): handle()\n#1 {main}",
        'failed_at' => now(),
    ]);

    Livewire::test(PlatformDashboard::class)
        ->assertSuccessful()
        ->assertSee('RuntimeException: boom')
        ->assertDontSee('LeakyJob')
        ->assertDontSee('hunter2')
        ->assertDontSee('/app/worker.php');
});

it('reports the pending queue backlog', function (): void {
    DB::table('jobs')->insert([
        ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->getTimestamp(), 'created_at' => now()->getTimestamp()],
        ['queue' => 'emails', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->getTimestamp(), 'created_at' => now()->getTimestamp()],
    ]);

    $health = app(PlatformDashboardService::class)->systemHealth();

    expect($health['queue']['pending_jobs_count'])->toBe(2);
});

it('reports the oldest pending backlog age', function (): void {
    DB::table('jobs')->insert([
        ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->subMinutes(10)->getTimestamp(), 'created_at' => now()->subMinutes(10)->getTimestamp()],
        ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->subMinutes(2)->getTimestamp(), 'created_at' => now()->subMinutes(2)->getTimestamp()],
    ]);

    $age = app(PlatformDashboardService::class)->systemHealth()['queue']['oldest_pending_age_seconds'];

    expect($age)->toBeGreaterThanOrEqual(600)
        ->and($age)->toBeLessThan(660);
});

it('reports a healthy scheduler heartbeat', function (): void {
    SchedulerHeartbeat::query()->create([
        'name' => 'application',
        'last_heartbeat_at' => now()->subSeconds(30),
    ]);

    $scheduler = app(PlatformDashboardService::class)->systemHealth()['scheduler'];

    expect($scheduler['status'])->toBe('healthy')
        ->and($scheduler['heartbeat_at'])->not->toBeNull()
        ->and($scheduler['age_seconds'])->toBeLessThanOrEqual(60);
});

it('reports an unhealthy scheduler heartbeat when stale or missing', function (): void {
    SchedulerHeartbeat::query()->create([
        'name' => 'application',
        'last_heartbeat_at' => now()->subMinutes(10),
    ]);

    $stale = app(PlatformDashboardService::class)->systemHealth()['scheduler'];
    expect($stale['status'])->toBe('unhealthy');

    SchedulerHeartbeat::query()->where('name', 'application')->delete();

    $missing = app(PlatformDashboardService::class)->systemHealth()['scheduler'];
    expect($missing['status'])->toBe('unhealthy')
        ->and($missing['heartbeat_at'])->toBeNull();
});

it('reports the database probe status', function (): void {
    expect(app(PlatformDashboardService::class)->databaseProbe())->toBe('OK');

    DB::shouldReceive('select')->andThrow(new RuntimeException('db down'));

    expect(app(PlatformDashboardService::class)->databaseProbe())->toBe('FAILED');
});

it('reports the cache probe status', function (): void {
    expect(app(PlatformDashboardService::class)->cacheProbe())->toBe('OK');

    Cache::shouldReceive('put')->andThrow(new RuntimeException('cache down'));

    expect(app(PlatformDashboardService::class)->cacheProbe())->toBe('FAILED');
});

it('shows the app environment and only an explicitly configured version', function (): void {
    config()->set('app.env', 'production');
    config()->set('app.version', '1.2.3');

    $app = app(PlatformDashboardService::class)->systemHealth()['app'];

    expect($app['environment'])->toBe('production')
        ->and($app['version'])->toBe('1.2.3');
});

it('hides the app version when not configured', function (): void {
    config()->set('app.version', null);

    $app = app(PlatformDashboardService::class)->systemHealth()['app'];

    expect($app['version'])->toBeNull();
});

it('renders the system health section for a platform admin', function (): void {
    Auth::guard('platform')->login(d1Admin());

    Livewire::test(PlatformDashboard::class)
        ->assertSuccessful()
        ->assertSee('System Health')
        ->assertSee('QUEUE')
        ->assertSee('SCHEDULER')
        ->assertSee('APPLICATION')
        ->assertSee('OK');
});

it('denies a non-platform user the system health section', function (): void {
    $tenant = d1Tenant();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner', 'is_platform_admin' => false]);
    Auth::guard('platform')->login($owner);

    Livewire::test(PlatformDashboard::class)->assertForbidden();
});

it('denies the system health section in Dedicated mode', function (): void {
    config()->set('deployment.mode', DeploymentMode::Dedicated->value);
    Auth::guard('platform')->login(d1Admin());

    Livewire::test(PlatformDashboard::class)->assertForbidden();
});

it('keeps system health computation to a bounded number of queries', function (): void {
    DB::table('failed_jobs')->insert([
        ['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'RuntimeException: one', 'failed_at' => now()],
        ['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'RuntimeException: two', 'failed_at' => now()],
    ]);
    DB::table('jobs')->insert([
        ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->getTimestamp(), 'created_at' => now()->getTimestamp()],
        ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->getTimestamp(), 'created_at' => now()->getTimestamp()],
    ]);
    SchedulerHeartbeat::query()->create(['name' => 'application', 'last_heartbeat_at' => now()]);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    app(PlatformDashboardService::class)->systemHealth();

    expect($queries)->toBeLessThanOrEqual(8);
});

it('lets a platform admin view recent platform activity', function (): void {
    Auth::guard('platform')->login(d1Admin());
    d1Tenant('active');

    Livewire::test(PlatformDashboard::class)
        ->assertSuccessful()
        ->assertSee('Recent Platform Activity');
});

it('denies recent platform activity in Dedicated mode', function (): void {
    config()->set('deployment.mode', DeploymentMode::Dedicated->value);
    Auth::guard('platform')->login(d1Admin());

    Livewire::test(PlatformDashboard::class)->assertForbidden();
});

it('renders an empty state when there is no recent platform activity', function (): void {
    Auth::guard('platform')->login(d1Admin());
    d1Tenant('active');

    Livewire::test(PlatformDashboard::class)
        ->assertSuccessful()
        ->assertSee('No recent platform activity.');
});

it('shows a subscription event in recent platform activity', function (): void {
    $tenant = d1Tenant('active');
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $growth = Plan::query()->where('slug', 'growth')->firstOrFail();
    $admin = d1Admin();

    SubscriptionEvent::query()->withoutGlobalScope('tenant')->create([
        'tenant_id' => $tenant->id,
        'type' => SubscriptionEventType::Upgraded,
        'from_plan_id' => $starter->id,
        'to_plan_id' => $growth->id,
        'actor_user_id' => $admin->id,
        'effective_at' => now()->subHour(),
        'note' => 'Seasonal promo',
    ]);

    $items = app(PlatformRecentActivityService::class)->items();

    expect($items)->toHaveCount(1)
        ->and($items[0]['badge'])->toBe('Upgraded')
        ->and($items[0]['label'])->toBe('Upgraded to '.$growth->name)
        ->and($items[0]['tenant'])->toBe((string) $tenant->name)
        ->and($items[0]['actor'])->toBe($admin->name)
        ->and($items[0]['note'])->toBe('Seasonal promo');
});

it('shows plan change approvals and rejections in recent platform activity', function (): void {
    $tenant = d1Tenant('active');
    $growth = Plan::query()->where('slug', 'growth')->firstOrFail();
    $admin = d1Admin();

    $approved = d1PlanRequest($tenant);
    activity('subscriptions')
        ->performedOn($approved)
        ->causedBy($admin)
        ->event('plan_change.approved')
        ->withProperties(['tenant_id' => $tenant->id, 'request_id' => $approved->id, 'requested_plan_id' => $growth->id])
        ->log('plan_change.approved');

    $rejected = d1PlanRequest($tenant);
    $tenancy = app(Tenancy::class);
    $tenancy->set($tenant);

    try {
        $rejected->update(['status' => PlanChangeRequestStatus::Rejected, 'rejection_reason' => 'Exceeds current limits.']);
    } finally {
        $tenancy->set(null);
    }

    activity('subscriptions')
        ->performedOn($rejected)
        ->causedByAnonymous()
        ->event('plan_change.rejected')
        ->withProperties(['tenant_id' => $tenant->id, 'request_id' => $rejected->id, 'requested_plan_id' => $growth->id])
        ->log('plan_change.rejected');

    $items = app(PlatformRecentActivityService::class)->items();
    $approvedEntry = collect($items)->firstWhere('badge', 'Plan request approved');
    $rejectedEntry = collect($items)->firstWhere('badge', 'Plan request rejected');

    expect($approvedEntry)->not->toBeNull()
        ->and($approvedEntry['label'])->toBe('Plan change approved to '.$growth->name)
        ->and($approvedEntry['tenant'])->toBe((string) $tenant->name)
        ->and($approvedEntry['actor'])->toBe($admin->name)
        ->and($rejectedEntry)->not->toBeNull()
        ->and($rejectedEntry['label'])->toBe('Plan change rejected to '.$growth->name)
        ->and($rejectedEntry['actor'])->toBe('System')
        ->and($rejectedEntry['note'])->toBe('Exceeds current limits.');
});

it('shows domain lifecycle activity in recent platform activity', function (): void {
    $tenant = d1Tenant('active');
    $admin = d1Admin();
    $activated = d1Domain($tenant, 'd5-shop.test', 'active');
    $failed = d1Domain($tenant, 'd5-failed.test', 'failed');

    activity('domains')
        ->performedOn($activated)
        ->causedBy($admin)
        ->event('domain.activated')
        ->log('domain.activated');

    activity('domains')
        ->performedOn($failed)
        ->causedByAnonymous()
        ->event('domain.verification_failed')
        ->withProperties([
            'domain_id' => $failed->id,
            'tenant_id' => $tenant->id,
            'failure_code' => 'wrong_txt',
            'failure_message' => 'No TXT record matched.',
        ])
        ->log('domain.verification_failed');

    $items = app(PlatformRecentActivityService::class)->items();
    $activatedEntry = collect($items)->firstWhere('badge', 'Domain activated');
    $failedEntry = collect($items)->firstWhere('badge', 'Domain verification failed');

    expect($activatedEntry)->not->toBeNull()
        ->and($activatedEntry['label'])->toBe('d5-shop.test activated')
        ->and($activatedEntry['tenant'])->toBe((string) $tenant->name)
        ->and($activatedEntry['actor'])->toBe($admin->name)
        ->and($failedEntry)->not->toBeNull()
        ->and($failedEntry['note'])->toBe('No TXT record matched.')
        ->and($failedEntry['actor'])->toBe('System');
});

it('orders recent platform activity newest first with a deterministic tie-break', function (): void {
    $tenant = d1Tenant('active');
    $admin = d1Admin();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $newer = now()->subHour();

    SubscriptionEvent::query()->withoutGlobalScope('tenant')->create([
        'tenant_id' => $tenant->id, 'type' => SubscriptionEventType::Renewed,
        'to_plan_id' => $starter->id, 'actor_user_id' => $admin->id, 'effective_at' => now()->subHours(3),
    ]);
    SubscriptionEvent::query()->withoutGlobalScope('tenant')->create([
        'tenant_id' => $tenant->id, 'type' => SubscriptionEventType::Expired,
        'to_plan_id' => $starter->id, 'actor_user_id' => $admin->id, 'effective_at' => $newer,
    ]);
    SubscriptionEvent::query()->withoutGlobalScope('tenant')->create([
        'tenant_id' => $tenant->id, 'type' => SubscriptionEventType::Reactivated,
        'to_plan_id' => $starter->id, 'actor_user_id' => $admin->id, 'effective_at' => $newer,
    ]);

    $labels = collect(app(PlatformRecentActivityService::class)->items())->pluck('label')->all();

    expect($labels)->toBe(['Reactivated', 'Expired', 'Renewed']);
});

it('caps recent platform activity to a bounded result count', function (): void {
    $tenant = d1Tenant('active');
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();

    for ($i = 0; $i < 15; $i++) {
        SubscriptionEvent::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id, 'type' => SubscriptionEventType::Renewed,
            'to_plan_id' => $starter->id, 'actor_user_id' => null,
            'effective_at' => now()->subMinutes($i),
        ]);
    }

    expect(app(PlatformRecentActivityService::class)->items())->toHaveCount(10);
});

it('keeps recent platform activity to a bounded number of queries', function (): void {
    $tenant = d1Tenant('active');
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $admin = d1Admin();

    for ($i = 0; $i < 12; $i++) {
        SubscriptionEvent::query()->withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenant->id, 'type' => SubscriptionEventType::Renewed,
            'to_plan_id' => $starter->id, 'actor_user_id' => $admin->id,
            'effective_at' => now()->subMinutes($i),
        ]);

        $request = d1PlanRequest($tenant);
        activity('subscriptions')
            ->performedOn($request)
            ->causedBy($admin)
            ->event('plan_change.approved')
            ->withProperties(['tenant_id' => $tenant->id, 'request_id' => $request->id, 'requested_plan_id' => $starter->id])
            ->log('plan_change.approved');

        activity('domains')
            ->performedOn(d1Domain($tenant, 'd5-'.$i.'.test', 'active'))
            ->causedBy($admin)
            ->event('domain.activated')
            ->log('domain.activated');
    }

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    app(PlatformRecentActivityService::class)->items();

    expect($queries)->toBeLessThanOrEqual(16);
});

it('displays the acting platform admin on recent activity', function (): void {
    Auth::guard('platform')->login($admin = d1Admin());
    $tenant = d1Tenant('active');
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();

    SubscriptionEvent::query()->withoutGlobalScope('tenant')->create([
        'tenant_id' => $tenant->id, 'type' => SubscriptionEventType::Renewed,
        'to_plan_id' => $starter->id, 'actor_user_id' => $admin->id, 'effective_at' => now(),
    ]);

    Livewire::test(PlatformDashboard::class)
        ->assertSuccessful()
        ->assertSee('Recent Platform Activity')
        ->assertSee($admin->name);
});

it('displays System when no actor is recorded', function (): void {
    Auth::guard('platform')->login(d1Admin());
    $tenant = d1Tenant('active');
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();

    SubscriptionEvent::query()->withoutGlobalScope('tenant')->create([
        'tenant_id' => $tenant->id, 'type' => SubscriptionEventType::Expired,
        'to_plan_id' => $starter->id, 'actor_user_id' => null, 'effective_at' => now(),
    ]);

    Livewire::test(PlatformDashboard::class)
        ->assertSuccessful()
        ->assertSee('Recent Platform Activity')
        ->assertSeeHtml('<span class="text-xs text-gray-500 dark:text-gray-400">System</span>');
});

it('never renders sensitive activity fields', function (): void {
    Auth::guard('platform')->login(d1Admin());
    $tenant = d1Tenant('active');
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();

    SubscriptionEvent::query()->withoutGlobalScope('tenant')->create([
        'tenant_id' => $tenant->id, 'type' => SubscriptionEventType::Renewed,
        'to_plan_id' => $starter->id, 'actor_user_id' => null, 'effective_at' => now(),
        'metadata' => ['token' => 'super-secret-event-token', 'password' => 'hunter2'],
    ]);

    $domain = d1Domain($tenant, 'd5-secret.test', 'pending');
    activity('domains')
        ->performedOn($domain)
        ->causedByAnonymous()
        ->event('domain.verification_failed')
        ->withProperties([
            'domain_id' => $domain->id,
            'tenant_id' => $tenant->id,
            'expected_digest' => 'deadbeefdigest',
            'verification_token_digest' => 'abc123digest',
            'failure_message' => 'Challenge expired.',
        ])
        ->log('domain.verification_failed');

    Livewire::test(PlatformDashboard::class)
        ->assertSuccessful()
        ->assertSee('Challenge expired.')
        ->assertDontSee('super-secret-event-token')
        ->assertDontSee('hunter2')
        ->assertDontSee('deadbeefdigest')
        ->assertDontSee('abc123digest');
});
