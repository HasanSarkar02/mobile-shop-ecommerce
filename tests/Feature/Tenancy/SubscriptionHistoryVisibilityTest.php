<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Enums\PlanChangeRequestStatus;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionPaymentIntent;
use App\Enums\SubscriptionStatus;
use App\Filament\Platform\Resources\TenantSubscriptionResource\Pages\ViewTenantSubscription;
use App\Filament\Platform\Support\SubscriptionHistoryPresenter;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\SubscriptionChargeService;
use App\Services\SubscriptionPaymentService;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('deployment.mode', DeploymentMode::SaaS->value);
    Filament::setCurrentPanel('platform');
    seedBootstrapPlans();
    app(Tenancy::class)->set(null);
});

function c5Admin(): User
{
    return User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);
}

function c5Tenant(): Tenant
{
    return Tenant::factory()->create([
        'subdomain' => 'c5-'.Str::lower(Str::random(8)),
        'status' => 'trial',
        'plan' => 'trial',
    ]);
}

function c5Plan(string $prefix, int $price): Plan
{
    return Plan::query()->create([
        'name' => $prefix.' '.Str::random(8),
        'slug' => Str::lower($prefix.'-'.Str::random(8)),
        'price' => $price,
        'billing_period' => 'monthly',
        'custom_domain_allowed' => true,
        'is_active' => true,
        'sort_order' => 1,
    ]);
}

function c5Event(
    Tenant $tenant,
    SubscriptionEventType $type,
    ?Plan $from = null,
    ?Plan $to = null,
    ?User $actor = null,
    ?CarbonInterface $effectiveAt = null,
    ?string $note = null,
    array $metadata = [],
): SubscriptionEvent {
    return SubscriptionEvent::query()->create([
        'tenant_id' => $tenant->id,
        'type' => $type,
        'from_plan_id' => $from?->id,
        'to_plan_id' => $to?->id,
        'note' => $note,
        'actor_user_id' => $actor?->id,
        'effective_at' => $effectiveAt ?? now(),
        'metadata' => $metadata,
    ]);
}

function c5PaymentRecorded(User $admin, Tenant $tenant, Plan $plan, string $reference): SubscriptionPayment
{
    $charge = app(SubscriptionChargeService::class)->createCharge($tenant, $plan, SubscriptionPaymentIntent::AssignPlan, $admin);

    return app(SubscriptionPaymentService::class)->record($charge, $reference, $admin);
}

function c5PaymentVerified(User $admin, Tenant $tenant, Plan $plan, string $reference): SubscriptionPayment
{
    $payment = c5PaymentRecorded($admin, $tenant, $plan, $reference);

    return app(SubscriptionPaymentService::class)->verify($payment, $admin);
}

function c5Decision(
    Tenant $tenant,
    Plan $plan,
    string $action,
    ?User $actor = null,
    ?CarbonInterface $at = null,
): void {
    $tenancy = app(Tenancy::class);
    $tenancy->set($tenant);

    try {
        $request = PlanChangeRequest::query()->create([
            'requested_plan_id' => $plan->id,
            'status' => $action === 'approved'
                ? PlanChangeRequestStatus::Approved
                : PlanChangeRequestStatus::Rejected,
        ]);
    } finally {
        $tenancy->set(null);
    }

    $builder = activity('subscriptions')
        ->performedOn($request)
        ->event('plan_change.'.$action)
        ->withProperties([
            'tenant_id' => $tenant->id,
            'request_id' => $request->id,
            'requested_plan_id' => $plan->id,
        ]);

    if ($actor !== null) {
        $builder->causedBy($actor);
    } else {
        $builder->causedByAnonymous();
    }

    $activity = $builder->log('plan_change.'.$action);

    if ($at !== null) {
        $activity->update(['created_at' => $at]);
    }
}

/** @return array<int, array<string, mixed>> */
function c5Entries(Tenant $tenant, int $limit = 50): array
{
    return SubscriptionHistoryPresenter::items($tenant, $limit);
}

it('merges subscription events, payments and plan-change decisions', function (): void {
    $admin = c5Admin();
    $tenant = c5Tenant();
    $planA = c5Plan('Alpha', 99000);
    $planB = c5Plan('Beta', 149000);
    c5Event($tenant, SubscriptionEventType::Subscribed, null, $planA, $admin);
    c5PaymentRecorded($admin, $tenant, $planA, 'TRX-MERGE-001');
    c5Decision($tenant, $planB, 'approved', $admin);

    $entries = c5Entries($tenant);

    expect(count($entries))->toBe(3)
        ->and(collect($entries)->pluck('kind')->all())->toContain('event', 'payment', 'decision');
});

it('orders entries chronologically descending', function (): void {
    $admin = c5Admin();
    $tenant = c5Tenant();
    $planA = c5Plan('Alpha', 99000);
    c5Event($tenant, SubscriptionEventType::Subscribed, null, $planA, $admin, now()->subDays(3));
    c5Event($tenant, SubscriptionEventType::Renewed, $planA, null, $admin, now()->subDays(2));
    c5Event($tenant, SubscriptionEventType::Expired, $planA, null, null, now()->subDay());
    $payment = c5PaymentRecorded($admin, $tenant, $planA, 'TRX-ORDER-001');
    $payment->update(['created_at' => now()->subHours(12), 'received_at' => now()->subHours(12)]);
    c5Decision($tenant, $planA, 'approved', $admin, now()->subHours(6));

    $entries = c5Entries($tenant);

    expect($entries[0]['kind'])->toBe('decision')
        ->and($entries[1]['kind'])->toBe('payment');

    $timestamps = array_map(fn (array $entry): int => $entry['sort_time']->getTimestamp(), $entries);
    $sorted = $timestamps;
    rsort($sorted);

    expect($timestamps)->toBe($sorted);
});

it('uses id descending as the tie-break for identical timestamps', function (): void {
    $admin = c5Admin();
    $tenant = c5Tenant();
    $planA = c5Plan('Alpha', 99000);
    $at = now();
    $first = c5Event($tenant, SubscriptionEventType::Subscribed, null, $planA, $admin, $at);
    $second = c5Event($tenant, SubscriptionEventType::Renewed, $planA, null, $admin, $at);

    $entries = c5Entries($tenant);

    expect($entries[0]['sort_id'])->toBe((int) $second->id)
        ->and($entries[1]['sort_id'])->toBe((int) $first->id);
});

it('exposes the plan transition for From → To', function (): void {
    $admin = c5Admin();
    $tenant = c5Tenant();
    $planA = c5Plan('Alpha', 99000);
    $planB = c5Plan('Beta', 149000);
    c5Event($tenant, SubscriptionEventType::Upgraded, $planA, $planB, $admin);

    $entry = collect(c5Entries($tenant))->first();

    expect($entry['kind'])->toBe('event')
        ->and($entry['from_plan'])->toBe($planA->name)
        ->and($entry['to_plan'])->toBe($planB->name);
});

it('renders System when an event has no actor', function (): void {
    $tenant = c5Tenant();
    $planA = c5Plan('Alpha', 99000);
    c5Event($tenant, SubscriptionEventType::Expired, $planA, null);

    $entry = collect(c5Entries($tenant))->first();

    expect($entry['actor'])->toBe('System')
        ->and($entry['is_system'])->toBeTrue();
});

it('renders the payment rejection reason and rejector', function (): void {
    $admin = c5Admin();
    $tenant = c5Tenant();
    $planA = c5Plan('Alpha', 99000);
    $payment = c5PaymentRecorded($admin, $tenant, $planA, 'TRX-REJ-001');
    app(SubscriptionPaymentService::class)->reject($payment, $admin, 'No funds.');

    $entry = collect(c5Entries($tenant))->firstWhere('kind', 'payment');

    expect($entry['status'])->toBe('Rejected')
        ->and($entry['rejected_reason'])->toBe('No funds.')
        ->and($entry['rejector'])->toBe($admin->name);
});

it('links a verified payment to its detail page', function (): void {
    $admin = c5Admin();
    $tenant = c5Tenant();
    $planA = c5Plan('Alpha', 99000);
    $payment = c5PaymentVerified($admin, $tenant, $planA, 'TRX-LINK-001');

    $entry = collect(c5Entries($tenant))->firstWhere('kind', 'payment');

    expect($entry['reference'])->toBe('TRX-LINK-001')
        ->and($entry['status'])->toBe('Verified')
        ->and($entry['url'])->toContain('subscription-payments')
        ->and($entry['url'])->toContain((string) $payment->id);
});

it('returns an empty timeline for a tenant with no activity', function (): void {
    $tenant = c5Tenant();

    expect(c5Entries($tenant))->toBe([]);
});

it('does not grow queries per entry', function (): void {
    $admin = c5Admin();
    $planA = c5Plan('Alpha', 99000);

    $smallTenant = c5Tenant();
    for ($i = 0; $i < 5; $i++) {
        c5Event($smallTenant, SubscriptionEventType::Renewed, $planA, null, $admin, now()->subDays($i + 1));
    }
    $smallCharge = app(SubscriptionChargeService::class)->createCharge($smallTenant, $planA, SubscriptionPaymentIntent::AssignPlan, $admin);
    app(SubscriptionPaymentService::class)->record($smallCharge, 'TRX-N1-SMALL-001', $admin);
    c5Decision($smallTenant, $planA, 'approved', $admin);

    $largeTenant = c5Tenant();
    for ($i = 0; $i < 15; $i++) {
        c5Event($largeTenant, SubscriptionEventType::Renewed, $planA, null, $admin, now()->subDays($i + 1));
    }
    $largeCharge = app(SubscriptionChargeService::class)->createCharge($largeTenant, $planA, SubscriptionPaymentIntent::AssignPlan, $admin);
    for ($i = 0; $i < 3; $i++) {
        app(SubscriptionPaymentService::class)->record($largeCharge, 'TRX-N1-LARGE-'.$i, $admin);
    }
    c5Decision($largeTenant, $planA, 'approved', $admin);
    c5Decision($largeTenant, $planA, 'rejected', $admin);

    DB::flushQueryLog();
    DB::enableQueryLog();

    c5Entries($smallTenant);
    $smallQueryCount = count(DB::getQueryLog());

    DB::flushQueryLog();

    c5Entries($largeTenant);
    $largeQueryCount = count(DB::getQueryLog());

    expect($largeQueryCount)->toBe($smallQueryCount)
        ->and($largeQueryCount)->toBeLessThan(20);
});

it('renders the timeline on the tenant subscription view page', function (): void {
    $admin = c5Admin();
    $tenant = c5Tenant();
    $planA = c5Plan('Alpha', 99000);
    $planB = c5Plan('Beta', 149000);
    c5PaymentVerified($admin, $tenant, $planA, 'TRX-TIMELINE-001');
    c5Decision($tenant, $planB, 'approved', $admin);
    Auth::guard('platform')->login($admin);

    $subscription = TenantSubscription::query()->where('tenant_id', $tenant->id)->firstOrFail();

    Livewire::test(ViewTenantSubscription::class, ['record' => (string) $subscription->id])
        ->assertOk()
        ->assertSee('TRX-TIMELINE-001')
        ->assertSee('View payment')
        ->assertSee('Plan change approved')
        ->assertSee('Verified');

    Auth::guard('platform')->logout();
});

it('renders the empty state on the tenant subscription view page when there is no activity', function (): void {
    $admin = c5Admin();
    $tenant = c5Tenant();
    $planA = c5Plan('Alpha', 99000);
    TenantSubscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $planA->id,
        'status' => SubscriptionStatus::Active,
        'current_period_starts_at' => now()->subDay(),
        'current_period_ends_at' => now()->addMonth(),
    ]);
    Auth::guard('platform')->login($admin);

    Livewire::test(ViewTenantSubscription::class, ['record' => (string) TenantSubscription::query()->where('tenant_id', $tenant->id)->first()->id])
        ->assertOk()
        ->assertSee('No subscription activity recorded yet.');

    Auth::guard('platform')->logout();
});
