<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Enums\SubscriptionChargeStatus;
use App\Enums\SubscriptionDiscountType;
use App\Enums\SubscriptionPaymentIntent;
use App\Filament\Platform\Resources\SubscriptionChargeResource;
use App\Filament\Platform\Resources\SubscriptionChargeResource\Pages\CreateSubscriptionCharge;
use App\Filament\Platform\Resources\SubscriptionChargeResource\Pages\ListSubscriptionCharges;
use App\Filament\Platform\Resources\SubscriptionChargeResource\Pages\ViewSubscriptionCharge;
use App\Filament\Platform\Support\PlatformMoney;
use App\Models\Plan;
use App\Models\SubscriptionCharge;
use App\Models\Tenant;
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

function c32Admin(): User
{
    return User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);
}

function c32Tenant(): Tenant
{
    return Tenant::factory()->create([
        'subdomain' => 'c32-'.Str::lower(Str::random(8)),
        'status' => 'trial',
        'plan' => 'trial',
    ]);
}

function c32Plan(string $prefix, int $price): Plan
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

function c32Charge(User $admin, Tenant $tenant, Plan $plan): SubscriptionCharge
{
    return app(SubscriptionChargeService::class)->createCharge($tenant, $plan, SubscriptionPaymentIntent::AssignPlan, $admin);
}

it('registers the SubscriptionChargeResource in the platform panel', function (): void {
    $resources = Filament::getPanel('platform')->getResources();

    expect(array_values($resources))->toContain(SubscriptionChargeResource::class);
});

it('registers the create and view page routes', function (): void {
    expect(route('filament.platform.resources.subscription-charges.create'))
        ->toContain('/platform/subscription-charges/create');

    expect(route('filament.platform.resources.subscription-charges.view', ['record' => 1]))
        ->toContain('/platform/subscription-charges');
});

it('exposes the Create Charge CreateAction on the list page', function (): void {
    $admin = c32Admin();
    Auth::guard('platform')->login($admin);

    Livewire::test(ListSubscriptionCharges::class)
        ->assertActionExists('create')
        ->assertActionHasLabel('create', 'Create Charge');

    Auth::guard('platform')->logout();
});

it('creates a charge through the Create page', function (): void {
    $admin = c32Admin();
    $tenant = c32Tenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    Auth::guard('platform')->login($admin);

    Livewire::test(CreateSubscriptionCharge::class)
        ->fillForm([
            'tenant_id' => $tenant->id,
            'intent' => SubscriptionPaymentIntent::AssignPlan->value,
            'plan_id' => $starter->id,
            'base_amount' => $starter->price / 100,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $charge = SubscriptionCharge::query()->first();
    expect($charge)->not->toBeNull()
        ->and($charge->tenant_id)->toBe($tenant->id)
        ->and($charge->plan_id)->toBe($starter->id)
        ->and($charge->intent)->toBe(SubscriptionPaymentIntent::AssignPlan)
        ->and($charge->base_amount)->toBe((int) $starter->price)
        ->and($charge->discount_amount)->toBe(0)
        ->and($charge->net_amount)->toBe((int) $starter->price)
        ->and($charge->paid_amount)->toBe(0)
        ->and($charge->status)->toBe(SubscriptionChargeStatus::Open);

    Auth::guard('platform')->logout();
});

it('creates a charge with a negotiated base and discount through the Create page', function (): void {
    $admin = c32Admin();
    $tenant = c32Tenant();
    $plan = c32Plan('Negotiated UI', 99000);
    Auth::guard('platform')->login($admin);

    Livewire::test(CreateSubscriptionCharge::class)
        ->fillForm([
            'tenant_id' => $tenant->id,
            'intent' => SubscriptionPaymentIntent::AssignPlan->value,
            'plan_id' => $plan->id,
            'base_amount' => 880.00,
            'discount_type' => SubscriptionDiscountType::Percentage->value,
            'discount_value' => 20,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $charge = SubscriptionCharge::query()->first();
    expect($charge)->not->toBeNull()
        ->and($charge->base_amount)->toBe(88000)
        ->and($charge->discount_type)->toBe(SubscriptionDiscountType::Percentage)
        ->and($charge->discount_value)->toBe(20)
        ->and($charge->discount_amount)->toBe(17600)
        ->and($charge->net_amount)->toBe(70400);

    Auth::guard('platform')->logout();
});

it('prefills the base amount from the selected plan', function (): void {
    $admin = c32Admin();
    $tenant = c32Tenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    Auth::guard('platform')->login($admin);

    Livewire::test(CreateSubscriptionCharge::class)
        ->fillForm([
            'tenant_id' => $tenant->id,
            'intent' => SubscriptionPaymentIntent::AssignPlan->value,
        ])
        ->set('data.plan_id', (string) $starter->id)
        ->assertSet('data.base_amount', $starter->price / 100);

    Auth::guard('platform')->logout();
});

it('calculates a percentage discount and net amount live', function (): void {
    $admin = c32Admin();
    $tenant = c32Tenant();
    $plan = c32Plan('Percent Preview', 99000);
    Auth::guard('platform')->login($admin);

    Livewire::test(CreateSubscriptionCharge::class)
        ->fillForm(['tenant_id' => $tenant->id, 'plan_id' => $plan->id])
        ->set('data.base_amount', '990.00')
        ->set('data.discount_type', SubscriptionDiscountType::Percentage->value)
        ->set('data.discount_value', '20')
        ->assertSet('data.discount_amount', 198.0)
        ->assertSet('data.net_amount', 792.0);

    Auth::guard('platform')->logout();
});

it('calculates a fixed discount and net amount live', function (): void {
    $admin = c32Admin();
    $tenant = c32Tenant();
    $plan = c32Plan('Fixed Preview', 99000);
    Auth::guard('platform')->login($admin);

    Livewire::test(CreateSubscriptionCharge::class)
        ->fillForm(['tenant_id' => $tenant->id, 'plan_id' => $plan->id])
        ->set('data.base_amount', '990.00')
        ->set('data.discount_type', SubscriptionDiscountType::Fixed->value)
        ->set('data.discount_value', '200.00')
        ->assertSet('data.discount_amount', 200.0)
        ->assertSet('data.net_amount', 790.0);

    Auth::guard('platform')->logout();
});

it('lists charges with the outstanding balance', function (): void {
    $admin = c32Admin();
    $tenant = c32Tenant();
    $plan = c32Plan('List Charge', 99000);
    $charge = c32Charge($admin, $tenant, $plan);
    $payment = app(SubscriptionPaymentService::class)->record($charge, 'TRX-C32-LIST-001', $admin, amount: 50000);
    app(SubscriptionPaymentService::class)->verify($payment, $admin);
    Auth::guard('platform')->login($admin);

    Livewire::test(ListSubscriptionCharges::class)
        ->assertCanSeeTableRecords([$charge])
        ->assertTableColumnStateSet('outstanding_amount', '৳490.00', $charge)
        ->assertTableColumnStateSet('status', 'Partially Paid', $charge);

    Auth::guard('platform')->logout();
});

it('shows a settled charge as fully paid on the list', function (): void {
    $admin = c32Admin();
    $tenant = c32Tenant();
    $plan = c32Plan('Settled List', 99000);
    $charge = c32Charge($admin, $tenant, $plan);
    $payment = app(SubscriptionPaymentService::class)->record($charge, 'TRX-C32-SETTLED-001', $admin);
    app(SubscriptionPaymentService::class)->verify($payment, $admin);
    Auth::guard('platform')->login($admin);

    Livewire::test(ListSubscriptionCharges::class)
        ->assertTableColumnStateSet('outstanding_amount', '৳0.00', $charge)
        ->assertTableColumnStateSet('status', 'Paid', $charge);

    Auth::guard('platform')->logout();
});

it('shows the settlement result on the charge view page', function (): void {
    $admin = c32Admin();
    $tenant = c32Tenant();
    $plan = c32Plan('Settle View', 99000);
    $charge = c32Charge($admin, $tenant, $plan);
    $payment = app(SubscriptionPaymentService::class)->record($charge, 'TRX-C32-VIEW-001', $admin);
    app(SubscriptionPaymentService::class)->verify($payment, $admin);
    Auth::guard('platform')->login($admin);

    Livewire::test(ViewSubscriptionCharge::class, ['record' => $charge->getRouteKey()])
        ->assertOk()
        ->assertSee('৳990.00')
        ->assertSee('Settled via Subscribed')
        ->assertSee('TRX-C32-VIEW-001');

    Auth::guard('platform')->logout();
});

it('offers a record payment action from an open charge view', function (): void {
    $admin = c32Admin();
    $tenant = c32Tenant();
    $plan = c32Plan('Record Action', 99000);
    $charge = c32Charge($admin, $tenant, $plan);
    Auth::guard('platform')->login($admin);

    Livewire::test(ViewSubscriptionCharge::class, ['record' => $charge->getRouteKey()])
        ->assertActionExists('recordPayment');

    Auth::guard('platform')->logout();
});

it('hides the record payment action once the charge is settled', function (): void {
    $admin = c32Admin();
    $tenant = c32Tenant();
    $plan = c32Plan('Record Hidden', 99000);
    $charge = c32Charge($admin, $tenant, $plan);
    $payment = app(SubscriptionPaymentService::class)->record($charge, 'TRX-C32-HIDDEN-001', $admin);
    app(SubscriptionPaymentService::class)->verify($payment, $admin);
    Auth::guard('platform')->login($admin);

    Livewire::test(ViewSubscriptionCharge::class, ['record' => $charge->getRouteKey()])
        ->assertActionHidden('recordPayment');

    Auth::guard('platform')->logout();
});

it('denies charge management to non-platform users', function (): void {
    $admin = c32Admin();
    $tenant = c32Tenant();
    $plan = c32Plan('Deny Charge', 99000);
    $charge = c32Charge($admin, $tenant, $plan);
    $staff = User::factory()->create(['is_platform_admin' => false, 'role' => 'staff']);
    Auth::guard('platform')->login($staff);

    expect(SubscriptionChargeResource::canViewAny())->toBeFalse()
        ->and(SubscriptionChargeResource::canCreate())->toBeFalse()
        ->and(SubscriptionChargeResource::canView($charge))->toBeFalse();

    Livewire::test(ListSubscriptionCharges::class)->assertForbidden();

    Auth::guard('platform')->logout();
});

it('denies charge management in Dedicated mode', function (): void {
    $admin = c32Admin();
    $tenant = c32Tenant();
    $plan = c32Plan('Dedicated Charge UI', 99000);
    $charge = c32Charge($admin, $tenant, $plan);
    Auth::guard('platform')->login($admin);
    config()->set('deployment.mode', DeploymentMode::Dedicated->value);

    expect(SubscriptionChargeResource::canViewAny())->toBeFalse()
        ->and(SubscriptionChargeResource::canCreate())->toBeFalse();

    Livewire::test(ListSubscriptionCharges::class)->assertForbidden();

    Auth::guard('platform')->logout();
});

it('formats minor units as BDT taka', function (): void {
    expect(PlatformMoney::format(99000))->toBe('৳990.00')
        ->and(PlatformMoney::format(19800))->toBe('৳198.00')
        ->and(PlatformMoney::format(990))->toBe('৳9.90')
        ->and(PlatformMoney::format(5))->toBe('৳0.05')
        ->and(PlatformMoney::format(0))->toBe('৳0.00')
        ->and(PlatformMoney::format(99000))->not->toBe('৳99000');
});
