<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Enums\SubscriptionChargeStatus;
use App\Enums\SubscriptionDiscountType;
use App\Enums\SubscriptionPaymentIntent;
use App\Models\Plan;
use App\Models\SubscriptionCharge;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionChargeService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    config()->set('deployment.mode', DeploymentMode::SaaS->value);
    seedBootstrapPlans();
    app(Tenancy::class)->set(null);
});

function c31Plan(string $prefix, int $price, string $billingPeriod = 'monthly', bool $active = true): Plan
{
    return Plan::query()->create([
        'name' => $prefix.' '.Str::random(8),
        'slug' => Str::lower($prefix.'-'.Str::random(8)),
        'price' => $price,
        'billing_period' => $billingPeriod,
        'custom_domain_allowed' => true,
        'is_active' => $active,
        'sort_order' => 1,
    ]);
}

function c31Tenant(string $status = 'trial'): Tenant
{
    return Tenant::factory()->create([
        'subdomain' => 'c31-'.Str::lower(Str::random(8)),
        'status' => $status,
        'plan' => 'trial',
    ]);
}

function c31Admin(): User
{
    return User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);
}

function c31Charge(
    Tenant $tenant,
    Plan $plan,
    SubscriptionChargeStatus $status = SubscriptionChargeStatus::Open,
): SubscriptionCharge {
    return SubscriptionCharge::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'intent' => SubscriptionPaymentIntent::AssignPlan,
        'base_amount' => $plan->price,
        'discount_amount' => 0,
        'net_amount' => $plan->price,
        'paid_amount' => 0,
        'status' => $status,
    ]);
}

it('defaults the base amount to the plan price', function (): void {
    $admin = c31Admin();
    $tenant = c31Tenant();
    $plan = c31Plan('Default Base', 99000);

    $charge = app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        SubscriptionPaymentIntent::AssignPlan,
        $admin,
    );

    expect($charge->base_amount)->toBe(99000)
        ->and($charge->discount_amount)->toBe(0)
        ->and($charge->net_amount)->toBe(99000)
        ->and($charge->paid_amount)->toBe(0)
        ->and($charge->status)->toBe(SubscriptionChargeStatus::Open)
        ->and($charge->outstandingAmount())->toBe(99000);
});

it('uses an explicit negotiated base amount', function (): void {
    $admin = c31Admin();
    $tenant = c31Tenant();
    $plan = c31Plan('Negotiated Base', 99000);

    $charge = app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        SubscriptionPaymentIntent::AssignPlan,
        $admin,
        baseAmount: 88000,
    );

    expect($charge->base_amount)->toBe(88000)
        ->and($charge->net_amount)->toBe(88000);
});

it('calculates a 20 percent discount', function (): void {
    $admin = c31Admin();
    $tenant = c31Tenant();
    $plan = c31Plan('Percent Off', 99000);

    $charge = app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        SubscriptionPaymentIntent::AssignPlan,
        $admin,
        discountType: SubscriptionDiscountType::Percentage,
        discountValue: 20,
    );

    expect($charge->base_amount)->toBe(99000)
        ->and($charge->discount_type)->toBe(SubscriptionDiscountType::Percentage)
        ->and($charge->discount_value)->toBe(20)
        ->and($charge->discount_amount)->toBe(19800)
        ->and($charge->net_amount)->toBe(79200)
        ->and($charge->outstandingAmount())->toBe(79200);
});

it('calculates a fixed discount', function (): void {
    $admin = c31Admin();
    $tenant = c31Tenant();
    $plan = c31Plan('Fixed Off', 99000);

    $charge = app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        SubscriptionPaymentIntent::AssignPlan,
        $admin,
        discountType: SubscriptionDiscountType::Fixed,
        discountValue: 20000,
    );

    expect($charge->base_amount)->toBe(99000)
        ->and($charge->discount_type)->toBe(SubscriptionDiscountType::Fixed)
        ->and($charge->discount_value)->toBe(20000)
        ->and($charge->discount_amount)->toBe(20000)
        ->and($charge->net_amount)->toBe(79000);
});

it('rejects a discount that exceeds the base amount', function (): void {
    $admin = c31Admin();
    $tenant = c31Tenant();
    $plan = c31Plan('Discount Cap', 99000);

    expect(fn () => app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        SubscriptionPaymentIntent::AssignPlan,
        $admin,
        discountType: SubscriptionDiscountType::Fixed,
        discountValue: 99000,
    ))->toThrow(DomainException::class, 'cannot equal or exceed');

    expect(SubscriptionCharge::query()->count())->toBe(0);
});

it('rejects a percentage discount above 100', function (): void {
    $admin = c31Admin();
    $tenant = c31Tenant();
    $plan = c31Plan('Percent Cap', 99000);

    expect(fn () => app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        SubscriptionPaymentIntent::AssignPlan,
        $admin,
        discountType: SubscriptionDiscountType::Percentage,
        discountValue: 101,
    ))->toThrow(DomainException::class, 'cannot exceed 100');

    expect(SubscriptionCharge::query()->count())->toBe(0);
});

it('rejects a zero or negative base amount', function (): void {
    $admin = c31Admin();
    $tenant = c31Tenant();
    $plan = c31Plan('Zero Base', 99000);

    foreach ([0, -100] as $base) {
        expect(fn () => app(SubscriptionChargeService::class)->createCharge(
            $tenant,
            $plan,
            SubscriptionPaymentIntent::AssignPlan,
            $admin,
            baseAmount: $base,
        ))->toThrow(DomainException::class, 'positive');
    }

    expect(SubscriptionCharge::query()->count())->toBe(0);
});

it('rejects a zero or negative discount value', function (): void {
    $admin = c31Admin();
    $tenant = c31Tenant();
    $plan = c31Plan('Zero Discount', 99000);

    foreach ([0, -100] as $value) {
        expect(fn () => app(SubscriptionChargeService::class)->createCharge(
            $tenant,
            $plan,
            SubscriptionPaymentIntent::AssignPlan,
            $admin,
            discountType: SubscriptionDiscountType::Fixed,
            discountValue: $value,
        ))->toThrow(DomainException::class, 'positive');
    }

    expect(SubscriptionCharge::query()->count())->toBe(0);
});

it('keeps the net amount greater than zero', function (): void {
    $admin = c31Admin();
    $tenant = c31Tenant();
    $plan = c31Plan('Net Guard', 99000);

    expect(fn () => app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        SubscriptionPaymentIntent::AssignPlan,
        $admin,
        discountType: SubscriptionDiscountType::Percentage,
        discountValue: 100,
    ))->toThrow(DomainException::class, 'cannot equal or exceed');

    expect(SubscriptionCharge::query()->count())->toBe(0);
});

it('freezes the stored amounts at creation', function (): void {
    $admin = c31Admin();
    $tenant = c31Tenant();
    $plan = c31Plan('Frozen', 99000);

    $charge = app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        SubscriptionPaymentIntent::AssignPlan,
        $admin,
        discountType: SubscriptionDiscountType::Percentage,
        discountValue: 20,
    );

    $plan->update(['price' => 120000]);

    $charge = $charge->fresh();

    expect($charge->base_amount)->toBe(99000)
        ->and($charge->discount_amount)->toBe(19800)
        ->and($charge->net_amount)->toBe(79200)
        ->and($charge->outstandingAmount())->toBe(79200);
});

it('does not recalculate existing charges when the plan price changes', function (): void {
    $admin = c31Admin();
    $tenant = c31Tenant();
    $plan = c31Plan('Historic', 99000);

    $first = app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        SubscriptionPaymentIntent::AssignPlan,
        $admin,
    );

    $plan->update(['price' => 150000]);

    $second = app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        SubscriptionPaymentIntent::ExtendSubscription,
        $admin,
    );

    expect($first->fresh()->net_amount)->toBe(99000)
        ->and($second->net_amount)->toBe(150000);
});

it('allows only one open or partially paid charge per tenant and intent', function (): void {
    $admin = c31Admin();
    $tenant = c31Tenant();
    $plan = c31Plan('Open Guard', 99000);

    app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        SubscriptionPaymentIntent::AssignPlan,
        $admin,
    );

    expect(fn () => app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        SubscriptionPaymentIntent::AssignPlan,
        $admin,
    ))->toThrow(DomainException::class, 'already exists');

    $partiallyPaid = c31Charge($tenant, $plan, SubscriptionChargeStatus::PartiallyPaid);
    expect(fn () => app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        SubscriptionPaymentIntent::AssignPlan,
        $admin,
    ))->toThrow(DomainException::class, 'already exists');
    expect($partiallyPaid->status)->toBe(SubscriptionChargeStatus::PartiallyPaid);

    expect(SubscriptionCharge::query()->where('tenant_id', $tenant->id)->where('intent', SubscriptionPaymentIntent::AssignPlan->value)->count())->toBe(2);
});

it('allows a different intent while one is open', function (): void {
    $admin = c31Admin();
    $tenant = c31Tenant();
    $plan = c31Plan('Intent Split', 99000);

    app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        SubscriptionPaymentIntent::AssignPlan,
        $admin,
    );

    $extension = app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        SubscriptionPaymentIntent::ExtendSubscription,
        $admin,
    );

    expect($extension->intent)->toBe(SubscriptionPaymentIntent::ExtendSubscription);
});

it('allows a new charge once the previous one is paid', function (): void {
    $admin = c31Admin();
    $tenant = c31Tenant();
    $plan = c31Plan('Paid Unblocks', 99000);

    app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        SubscriptionPaymentIntent::AssignPlan,
        $admin,
    )->update(['status' => SubscriptionChargeStatus::Paid]);

    $charge = app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        SubscriptionPaymentIntent::AssignPlan,
        $admin,
    );

    expect($charge->status)->toBe(SubscriptionChargeStatus::Open)
        ->and(SubscriptionCharge::query()->count())->toBe(2);
});

it('rejects charge creation for non-platform and inactive platform admins', function (): void {
    $tenant = c31Tenant();
    $plan = c31Plan('Guard Charge', 99000);
    $staff = User::factory()->create(['is_platform_admin' => false, 'role' => 'staff']);
    $inactiveAdmin = User::factory()->create(['is_platform_admin' => true, 'is_active' => false, 'app_authentication_secret' => 'test-secret']);

    foreach ([$staff, $inactiveAdmin] as $actor) {
        expect(fn () => app(SubscriptionChargeService::class)->createCharge(
            $tenant,
            $plan,
            SubscriptionPaymentIntent::AssignPlan,
            $actor,
        ))->toThrow(DomainException::class, 'Platform Admin');
    }

    expect(SubscriptionCharge::query()->count())->toBe(0);
});

it('rejects charge creation in Dedicated mode', function (): void {
    $admin = c31Admin();
    $tenant = c31Tenant();
    $plan = c31Plan('Dedicated Charge', 99000);
    config()->set('deployment.mode', DeploymentMode::Dedicated->value);

    expect(fn () => app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        SubscriptionPaymentIntent::AssignPlan,
        $admin,
    ))->toThrow(DomainException::class, 'Platform Admin');

    expect(SubscriptionCharge::query()->count())->toBe(0);
});

it('logs charge creation with the billing context', function (): void {
    $admin = c31Admin();
    $tenant = c31Tenant();
    $plan = c31Plan('Audited Charge', 99000);

    $charge = app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        SubscriptionPaymentIntent::AssignPlan,
        $admin,
        discountType: SubscriptionDiscountType::Percentage,
        discountValue: 20,
        note: 'Promotional period',
    );

    $activity = Activity::query()
        ->where('log_name', 'subscription-charges')
        ->where('subject_type', SubscriptionCharge::class)
        ->where('subject_id', $charge->id)
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->event)->toBe('subscription-charge.created')
        ->and($activity->causer_id)->toBe($admin->id);

    $properties = $activity->properties->all();

    expect($properties['tenant_id'])->toBe($tenant->id)
        ->and($properties['plan_id'])->toBe($plan->id)
        ->and($properties['intent'])->toBe(SubscriptionPaymentIntent::AssignPlan->value)
        ->and($properties['base_amount'])->toBe(99000)
        ->and($properties['discount_type'])->toBe(SubscriptionDiscountType::Percentage->value)
        ->and($properties['discount_value'])->toBe(20)
        ->and($properties['discount_amount'])->toBe(19800)
        ->and($properties['net_amount'])->toBe(79200);
});
