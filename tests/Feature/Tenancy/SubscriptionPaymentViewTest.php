<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Enums\SubscriptionPaymentIntent;
use App\Filament\Platform\Resources\SubscriptionPaymentResource;
use App\Filament\Platform\Resources\SubscriptionPaymentResource\Pages\ViewSubscriptionPayment;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
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

function c5pvAdmin(): User
{
    return User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);
}

function c5pvTenant(): Tenant
{
    return Tenant::factory()->create([
        'subdomain' => 'c5pv-'.Str::lower(Str::random(8)),
        'status' => 'trial',
        'plan' => 'trial',
    ]);
}

function c5pvVerifiedPayment(User $admin, Tenant $tenant, Plan $plan, string $reference): SubscriptionPayment
{
    $charge = app(SubscriptionChargeService::class)->createCharge($tenant, $plan, SubscriptionPaymentIntent::AssignPlan, $admin);

    $payment = app(SubscriptionPaymentService::class)->record(
        $charge,
        $reference,
        $admin,
        paymentMethod: 'bkash',
    );

    return app(SubscriptionPaymentService::class)->verify($payment, $admin);
}

function c5pvRejectedPayment(User $admin, Tenant $tenant, Plan $plan, string $reference): SubscriptionPayment
{
    $charge = app(SubscriptionChargeService::class)->createCharge($tenant, $plan, SubscriptionPaymentIntent::AssignPlan, $admin);

    $payment = app(SubscriptionPaymentService::class)->record($charge, $reference, $admin);

    return app(SubscriptionPaymentService::class)->reject($payment, $admin, 'Funds not received.');
}

it('lets a Platform Admin view a payment detail page', function (): void {
    $admin = c5pvAdmin();
    $tenant = c5pvTenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $payment = c5pvVerifiedPayment($admin, $tenant, $starter, 'TRX-PV-001');
    Auth::guard('platform')->login($admin);

    Livewire::test(ViewSubscriptionPayment::class, ['record' => $payment->getRouteKey()])
        ->assertOk()
        ->assertSee('TRX-PV-001');

    Auth::guard('platform')->logout();
});

it('renders all important payment fields', function (): void {
    $admin = c5pvAdmin();
    $tenant = c5pvTenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $payment = c5pvVerifiedPayment($admin, $tenant, $starter, 'TRX-PV-002');
    Auth::guard('platform')->login($admin);

    Livewire::test(ViewSubscriptionPayment::class, ['record' => $payment->getRouteKey()])
        ->assertSee($tenant->name)
        ->assertSee('Assign Plan')
        ->assertSee('Starter')
        ->assertSee('BDT')
        ->assertSee('manual')
        ->assertSee('bkash')
        ->assertSee('TRX-PV-002')
        ->assertSee('Verified');

    Auth::guard('platform')->logout();
});

it('renders payment actor relations', function (): void {
    $admin = c5pvAdmin();
    $tenant = c5pvTenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $payment = c5pvRejectedPayment($admin, $tenant, $starter, 'TRX-PV-003');
    Auth::guard('platform')->login($admin);

    Livewire::test(ViewSubscriptionPayment::class, ['record' => $payment->getRouteKey()])
        ->assertSee($admin->name)
        ->assertSee('Rejected')
        ->assertSee('Funds not received.');

    Auth::guard('platform')->logout();
});

it('denies non-platform users the payment detail page', function (): void {
    $admin = c5pvAdmin();
    $tenant = c5pvTenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $payment = c5pvVerifiedPayment($admin, $tenant, $starter, 'TRX-PV-004');
    $staff = User::factory()->create(['is_platform_admin' => false, 'role' => 'staff']);
    Auth::guard('platform')->login($staff);

    expect(SubscriptionPaymentResource::canView($payment))->toBeFalse();

    Livewire::test(ViewSubscriptionPayment::class, ['record' => $payment->getRouteKey()])->assertForbidden();

    Auth::guard('platform')->logout();
});

it('denies the payment detail page in Dedicated mode', function (): void {
    $admin = c5pvAdmin();
    $tenant = c5pvTenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $payment = c5pvVerifiedPayment($admin, $tenant, $starter, 'TRX-PV-005');
    Auth::guard('platform')->login($admin);
    config()->set('deployment.mode', DeploymentMode::Dedicated->value);

    expect(SubscriptionPaymentResource::canView($payment))->toBeFalse();

    Livewire::test(ViewSubscriptionPayment::class, ['record' => $payment->getRouteKey()])->assertForbidden();

    Auth::guard('platform')->logout();
});

it('keeps terminal payments read-only', function (): void {
    $admin = c5pvAdmin();
    $tenant = c5pvTenant();
    $starter = Plan::query()->where('slug', 'starter')->firstOrFail();
    $payment = c5pvVerifiedPayment($admin, $tenant, $starter, 'TRX-PV-006');
    Auth::guard('platform')->login($admin);

    expect(SubscriptionPaymentResource::canEdit($payment))->toBeFalse()
        ->and(SubscriptionPaymentResource::canDelete($payment))->toBeFalse();

    Livewire::test(ViewSubscriptionPayment::class, ['record' => $payment->getRouteKey()])
        ->assertOk()
        ->assertSee('Verified');

    Auth::guard('platform')->logout();
});
