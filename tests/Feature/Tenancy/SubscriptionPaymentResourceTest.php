<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Enums\SubscriptionPaymentIntent;
use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Filament\Platform\Resources\SubscriptionPaymentResource;
use App\Filament\Platform\Resources\SubscriptionPaymentResource\Pages\CreateSubscriptionPayment;
use App\Filament\Platform\Resources\SubscriptionPaymentResource\Pages\ListSubscriptionPayments;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\SubscriptionChargeService;
use App\Services\SubscriptionPaymentService;
use App\Support\Tenancy\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('deployment.mode', DeploymentMode::SaaS->value);
    Filament::setCurrentPanel('platform');
    seedBootstrapPlans();
    app(Tenancy::class)->set(null);
});

function c25rAdmin(): User
{
    return User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);
}

function c25rTenant(string $status = 'trial'): Tenant
{
    return Tenant::factory()->create([
        'subdomain' => 'c25r-'.Str::lower(Str::random(8)),
        'status' => $status,
        'plan' => 'trial',
    ]);
}

function c25rPendingPayment(Tenant $tenant, Plan $plan, string $reference): SubscriptionPayment
{
    $admin = c25rAdmin();
    $charge = app(SubscriptionChargeService::class)->createCharge($tenant, $plan, SubscriptionPaymentIntent::AssignPlan, $admin);

    return app(SubscriptionPaymentService::class)->record($charge, $reference, $admin);
}

it('registers the SubscriptionPaymentResource in the platform panel', function (): void {
    $resources = Filament::getPanel('platform')->getResources();

    expect(array_values($resources))->toContain(SubscriptionPaymentResource::class);
});

it('registers the create page route', function (): void {
    expect(route('filament.platform.resources.subscription-payments.create'))
        ->toContain('/platform/subscription-payments/create');
});

it('exposes the Record Manual Payment CreateAction on the list page', function (): void {
    $admin = c25rAdmin();
    Auth::guard('platform')->login($admin);

    Livewire::test(ListSubscriptionPayments::class)
        ->assertActionExists('create')
        ->assertActionHasLabel('create', 'Record Manual Payment');

    Auth::guard('platform')->logout();
});

it('lists subscription payments across tenants', function (): void {
    $admin = c25rAdmin();
    $tenantA = Tenant::factory()->create(['name' => 'Alpha Pay', 'subdomain' => 'alpha-pay', 'status' => 'active']);
    $tenantB = Tenant::factory()->create(['name' => 'Beta Pay', 'subdomain' => 'beta-pay', 'status' => 'trial']);
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $growth = Plan::query()->where('slug', 'growth')->firstOrFail();
    $paymentA = c25rPendingPayment($tenantA, $starter, 'TRX-LIST-001');
    $paymentB = c25rPendingPayment($tenantB, $growth, 'TRX-LIST-002');
    Auth::guard('platform')->login($admin);

    Livewire::test(ListSubscriptionPayments::class)
        ->assertCanSeeTableRecords([$paymentA, $paymentB])
        ->assertTableColumnStateSet('tenant.name', 'Alpha Pay', $paymentA)
        ->assertTableColumnStateSet('status', SubscriptionPaymentStatus::Pending, $paymentA);

    Auth::guard('platform')->logout();
});

it('filters payments by status', function (): void {
    $admin = c25rAdmin();
    $tenant = c25rTenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $charge = app(SubscriptionChargeService::class)->createCharge($tenant, $starter, SubscriptionPaymentIntent::AssignPlan, $admin);
    $pending = app(SubscriptionPaymentService::class)->record($charge, 'TRX-FLT-001', $admin);
    $rejected = app(SubscriptionPaymentService::class)->record($charge, 'TRX-FLT-002', $admin);
    app(SubscriptionPaymentService::class)->reject($rejected, $admin, 'No funds.');
    Auth::guard('platform')->login($admin);

    Livewire::test(ListSubscriptionPayments::class)
        ->filterTable('status', SubscriptionPaymentStatus::Rejected->value)
        ->assertCanSeeTableRecords([$rejected])
        ->assertCanNotSeeTableRecords([$pending]);

    Auth::guard('platform')->logout();
});

it('creates a payment through the Create page', function (): void {
    $admin = c25rAdmin();
    $tenant = c25rTenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $charge = app(SubscriptionChargeService::class)->createCharge($tenant, $starter, SubscriptionPaymentIntent::AssignPlan, $admin);
    Auth::guard('platform')->login($admin);

    Livewire::test(CreateSubscriptionPayment::class)
        ->fillForm([
            'subscription_charge_id' => $charge->id,
            'payment_method' => 'bkash',
            'reference' => 'trx-ui-001',
            'amount' => $starter->price / 100,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $payment = SubscriptionPayment::query()->first();
    expect($payment)->not->toBeNull()
        ->and($payment->tenant_id)->toBe($tenant->id)
        ->and($payment->plan_id)->toBe($starter->id)
        ->and($payment->intent)->toBe(SubscriptionPaymentIntent::AssignPlan)
        ->and($payment->subscription_charge_id)->toBe($charge->id)
        ->and($payment->status)->toBe(SubscriptionPaymentStatus::Pending)
        ->and($payment->provider)->toBe('manual')
        ->and($payment->payment_method)->toBe('bkash')
        ->and($payment->amount)->toBe((int) $starter->price)
        ->and($payment->reference)->toBe('TRX-UI-001')
        ->and($payment->created_by)->toBe($admin->id);

    Auth::guard('platform')->logout();
});

it('prefills the amount from the selected charge and records minor units', function (): void {
    $admin = c25rAdmin();
    $tenant = c25rTenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $charge = app(SubscriptionChargeService::class)->createCharge($tenant, $starter, SubscriptionPaymentIntent::AssignPlan, $admin);
    Auth::guard('platform')->login($admin);

    Livewire::test(CreateSubscriptionPayment::class)
        ->fillForm([
            'payment_method' => 'bkash',
            'reference' => 'trx-prefill-001',
        ])
        ->set('data.subscription_charge_id', (string) $charge->id)
        ->assertSet('data.amount', $starter->price / 100)
        ->call('create')
        ->assertHasNoFormErrors();

    $payment = SubscriptionPayment::query()->first();
    expect($payment)->not->toBeNull()
        ->and($payment->amount)->toBe((int) $starter->price)
        ->and($payment->subscription_charge_id)->toBe($charge->id)
        ->and($payment->reference)->toBe('TRX-PREFILL-001')
        ->and($payment->status)->toBe(SubscriptionPaymentStatus::Pending);

    Auth::guard('platform')->logout();
});

it('verifies a payment recorded through the prefilled plan flow', function (): void {
    $admin = c25rAdmin();
    $tenant = c25rTenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $payment = c25rPendingPayment($tenant, $starter, 'TRX-VFY-001');
    Auth::guard('platform')->login($admin);

    Livewire::test(ListSubscriptionPayments::class)
        ->callTableAction('verify', $payment->getRouteKey())
        ->assertHasNoTableActionErrors();

    $payment = $payment->fresh();
    expect($payment->status)->toBe(SubscriptionPaymentStatus::Verified)
        ->and($payment->verified_by)->toBe($admin->id)
        ->and($payment->received_at)->not->toBeNull();

    $subscription = TenantSubscription::query()->where('tenant_id', $tenant->id)->first();
    expect($subscription)->not->toBeNull()
        ->and($subscription->plan_id)->toBe($starter->id)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($tenant->refresh()->status)->toBe('active')
        ->and($tenant->refresh()->plan)->toBe('starter');

    Auth::guard('platform')->logout();
});

it('rejects a payment through the Reject action without mutating the subscription', function (): void {
    $admin = c25rAdmin();
    $tenant = c25rTenant('active');
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    TenantSubscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $starter->id,
        'status' => SubscriptionStatus::Active,
        'current_period_starts_at' => now()->subDay(),
        'current_period_ends_at' => now()->addMonth(),
    ]);
    $payment = c25rPendingPayment($tenant, $starter, 'TRX-REJ-001');
    Auth::guard('platform')->login($admin);

    Livewire::test(ListSubscriptionPayments::class)
        ->callTableAction('reject', $payment->getRouteKey(), ['reason' => 'Funds not received.'])
        ->assertHasNoTableActionErrors();

    $payment = $payment->fresh();
    expect($payment->status)->toBe(SubscriptionPaymentStatus::Rejected)
        ->and($payment->rejected_by)->toBe($admin->id)
        ->and($payment->rejected_at)->not->toBeNull()
        ->and($payment->rejected_reason)->toBe('Funds not received.');

    expect(TenantSubscription::query()->where('tenant_id', $tenant->id)->firstOrFail()->status)
        ->toBe(SubscriptionStatus::Active);

    Auth::guard('platform')->logout();
});

it('denies payment management to non-platform users', function (): void {
    $tenant = c25rTenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $payment = c25rPendingPayment($tenant, $starter, 'TRX-DENY-001');
    $staff = User::factory()->create(['is_platform_admin' => false, 'role' => 'staff']);
    Auth::guard('platform')->login($staff);

    expect(SubscriptionPaymentResource::canViewAny())->toBeFalse()
        ->and(SubscriptionPaymentResource::canCreate())->toBeFalse();

    Livewire::test(ListSubscriptionPayments::class)->assertForbidden();

    Auth::guard('platform')->logout();
});

it('denies payment management in Dedicated mode', function (): void {
    $admin = c25rAdmin();
    $tenant = c25rTenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $payment = c25rPendingPayment($tenant, $starter, 'TRX-DED-001');
    Auth::guard('platform')->login($admin);
    config()->set('deployment.mode', DeploymentMode::Dedicated->value);

    expect(SubscriptionPaymentResource::canViewAny())->toBeFalse()
        ->and(SubscriptionPaymentResource::canCreate())->toBeFalse();

    Livewire::test(ListSubscriptionPayments::class)->assertForbidden();

    Auth::guard('platform')->logout();
});
