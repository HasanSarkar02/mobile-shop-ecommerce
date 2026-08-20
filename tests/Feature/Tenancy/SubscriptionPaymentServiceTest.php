<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Enums\SubscriptionChargeStatus;
use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionPaymentIntent;
use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\SubscriptionCharge;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\SubscriptionChargeService;
use App\Services\SubscriptionPaymentService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

beforeEach(function (): void {
    seedBootstrapPlans();
    app(Tenancy::class)->set(null);
});

function c25Plan(string $prefix, int $price, string $billingPeriod = 'monthly', bool $active = true): Plan
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

function c25Tenant(string $status = 'trial'): Tenant
{
    return Tenant::factory()->create([
        'subdomain' => 'c25-'.Str::lower(Str::random(8)),
        'status' => $status,
        'plan' => 'trial',
    ]);
}

function c25Subscription(Tenant $tenant, Plan $plan): TenantSubscription
{
    return TenantSubscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'current_period_starts_at' => now()->subDay(),
        'current_period_ends_at' => now()->addMonth(),
    ]);
}

function c25Admin(): User
{
    return User::factory()->create(['is_platform_admin' => true, 'app_authentication_secret' => 'test-secret']);
}

function c25Charge(
    User $admin,
    Tenant $tenant,
    Plan $plan,
    SubscriptionPaymentIntent $intent = SubscriptionPaymentIntent::AssignPlan,
    ?int $baseAmount = null,
): SubscriptionCharge {
    return app(SubscriptionChargeService::class)->createCharge(
        $tenant,
        $plan,
        $intent,
        $admin,
        baseAmount: $baseAmount,
    );
}

/** @return Collection<int, SubscriptionEvent> */
function c25Events(Tenant $tenant): Collection
{
    return SubscriptionEvent::query()
        ->withoutGlobalScope('tenant')
        ->where('tenant_id', $tenant->id)
        ->get();
}

it('records an assign-plan payment as pending against the charge', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Record Assign', 99000);
    $charge = c25Charge($admin, $tenant, $plan);

    $payment = app(SubscriptionPaymentService::class)->record($charge, 'TRX-ASSIGN-001', $admin, paymentMethod: 'bkash');

    expect($payment->status)->toBe(SubscriptionPaymentStatus::Pending)
        ->and($payment->intent)->toBe(SubscriptionPaymentIntent::AssignPlan)
        ->and($payment->provider)->toBe('manual')
        ->and($payment->payment_method)->toBe('bkash')
        ->and($payment->currency)->toBe('BDT')
        ->and($payment->amount)->toBe(99000)
        ->and($payment->reference)->toBe('TRX-ASSIGN-001')
        ->and($payment->created_by)->toBe($admin->id)
        ->and($payment->plan_id)->toBe($plan->id)
        ->and($payment->subscription_charge_id)->toBe($charge->id)
        ->and($payment->extension_days)->toBeNull();

    expect(TenantSubscription::query()->where('tenant_id', $tenant->id)->count())->toBe(0);
});

it('records an extend-subscription payment as pending', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Record Extend', 99000);
    $charge = c25Charge($admin, $tenant, $plan, SubscriptionPaymentIntent::ExtendSubscription);

    $payment = app(SubscriptionPaymentService::class)->record(
        $charge,
        'TRX-EXTEND-001',
        $admin,
        paymentMethod: 'nagad',
        extensionDays: 30,
    );

    expect($payment->status)->toBe(SubscriptionPaymentStatus::Pending)
        ->and($payment->intent)->toBe(SubscriptionPaymentIntent::ExtendSubscription)
        ->and($payment->extension_days)->toBe(30)
        ->and($payment->payment_method)->toBe('nagad');
});

it('defaults the amount to the outstanding balance', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Default Amount', 99000);
    $charge = c25Charge($admin, $tenant, $plan);

    $payment = app(SubscriptionPaymentService::class)->record($charge, 'TRX-DEF-001', $admin);

    expect($payment->amount)->toBe(99000)
        ->and($payment->subscription_charge_id)->toBe($charge->id);
});

it('applies a partial payment and marks the charge partially paid', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Partial Pay', 99000);
    $charge = c25Charge($admin, $tenant, $plan);
    $payment = app(SubscriptionPaymentService::class)->record($charge, 'TRX-PARTIAL-001', $admin, amount: 50000);

    $verified = app(SubscriptionPaymentService::class)->verify($payment, $admin);

    expect($verified->status)->toBe(SubscriptionPaymentStatus::Verified)
        ->and($verified->subscription_charge_id)->toBe($charge->id)
        ->and($charge->fresh()->status)->toBe(SubscriptionChargeStatus::PartiallyPaid)
        ->and($charge->fresh()->paid_amount)->toBe(50000)
        ->and($charge->fresh()->outstandingAmount())->toBe(49000);

    expect(TenantSubscription::query()->where('tenant_id', $tenant->id)->count())->toBe(0)
        ->and(c25Events($tenant)->count())->toBe(0);
});

it('applies multiple payments to a charge without exceeding the net amount', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Concurrent Pay', 99000);
    $charge = c25Charge($admin, $tenant, $plan);
    $first = app(SubscriptionPaymentService::class)->record($charge, 'TRX-CON-001', $admin, amount: 49000);
    $second = app(SubscriptionPaymentService::class)->record($charge, 'TRX-CON-002', $admin, amount: 50000);

    app(SubscriptionPaymentService::class)->verify($first, $admin);
    app(SubscriptionPaymentService::class)->verify($second, $admin);

    expect($charge->fresh()->status)->toBe(SubscriptionChargeStatus::Paid)
        ->and($charge->fresh()->paid_amount)->toBe(99000)
        ->and($charge->fresh()->outstandingAmount())->toBe(0);
});

it('rejects an overpayment against the outstanding balance', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Over Pay', 99000);
    $charge = c25Charge($admin, $tenant, $plan);

    expect(fn () => app(SubscriptionPaymentService::class)->record($charge, 'TRX-OVER-001', $admin, amount: 100000))
        ->toThrow(DomainException::class, 'cannot exceed');

    expect(SubscriptionPayment::query()->count())->toBe(0)
        ->and($charge->fresh()->status)->toBe(SubscriptionChargeStatus::Open);
});

it('rejects a verification that would exceed the outstanding balance', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Over Verify', 99000);
    $charge = c25Charge($admin, $tenant, $plan);
    $first = app(SubscriptionPaymentService::class)->record($charge, 'TRX-OV-001', $admin, amount: 60000);
    $second = app(SubscriptionPaymentService::class)->record($charge, 'TRX-OV-002', $admin, amount: 60000);

    app(SubscriptionPaymentService::class)->verify($first, $admin);

    expect(fn () => app(SubscriptionPaymentService::class)->verify($second, $admin))
        ->toThrow(DomainException::class, 'cannot exceed');

    expect($second->fresh()->status)->toBe(SubscriptionPaymentStatus::Pending)
        ->and($charge->fresh()->status)->toBe(SubscriptionChargeStatus::PartiallyPaid)
        ->and($charge->fresh()->paid_amount)->toBe(60000);
});

it('rejects recording a payment against a settled charge', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Settled Charge', 99000);
    $charge = c25Charge($admin, $tenant, $plan);
    $charge->update(['status' => SubscriptionChargeStatus::Paid, 'paid_amount' => 99000]);

    expect(fn () => app(SubscriptionPaymentService::class)->record($charge, 'TRX-SETTLED-001', $admin))
        ->toThrow(DomainException::class, 'open or partially paid');

    expect(SubscriptionPayment::query()->count())->toBe(0);
});

it('rejects a duplicate provider reference and normalizes case', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Record Dup', 99000);
    $charge = c25Charge($admin, $tenant, $plan);

    app(SubscriptionPaymentService::class)->record($charge, 'trx-dup-001', $admin);

    expect(fn () => app(SubscriptionPaymentService::class)->record($charge, 'TRX-DUP-001', $admin))
        ->toThrow(DomainException::class, 'already recorded');

    expect(SubscriptionPayment::query()->count())->toBe(1);
});

it('rejects recording a payment for an inactive plan', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Inactive Pay', 1000);
    $charge = c25Charge($admin, $tenant, $plan);
    $plan->update(['is_active' => false]);

    expect(fn () => app(SubscriptionPaymentService::class)->record($charge, 'TRX-INACTIVE-001', $admin))
        ->toThrow(DomainException::class, 'not available');

    expect(SubscriptionPayment::query()->count())->toBe(0);
});

it('rejects a non-positive amount', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Record Amount', 99000);
    $charge = c25Charge($admin, $tenant, $plan);

    expect(fn () => app(SubscriptionPaymentService::class)->record($charge, 'TRX-ZERO-001', $admin, amount: 0))
        ->toThrow(DomainException::class, 'positive');
    expect(fn () => app(SubscriptionPaymentService::class)->record($charge, 'TRX-NEG-001', $admin, amount: -100))
        ->toThrow(DomainException::class, 'positive');

    expect(SubscriptionPayment::query()->count())->toBe(0);
});

it('verifies an assign-plan payment and assigns the plan', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Verify Assign', 99000);
    $charge = c25Charge($admin, $tenant, $plan);
    $payment = app(SubscriptionPaymentService::class)->record($charge, 'TRX-VA-001', $admin);

    $verified = app(SubscriptionPaymentService::class)->verify($payment, $admin);

    expect($verified->status)->toBe(SubscriptionPaymentStatus::Verified)
        ->and($verified->verified_by)->toBe($admin->id)
        ->and($verified->received_at)->not->toBeNull()
        ->and($charge->fresh()->status)->toBe(SubscriptionChargeStatus::Paid)
        ->and($charge->fresh()->paid_amount)->toBe(99000)
        ->and($charge->fresh()->outstandingAmount())->toBe(0);

    $subscription = TenantSubscription::query()->where('tenant_id', $tenant->id)->first();
    expect($subscription)->not->toBeNull()
        ->and($subscription->plan_id)->toBe($plan->id)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active);

    expect($tenant->refresh()->status)->toBe('active')
        ->and($tenant->refresh()->plan)->toBe($plan->slug);

    $event = c25Events($tenant)->firstWhere('type', SubscriptionEventType::Subscribed);
    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($admin->id)
        ->and($event->effective_at)->not->toBeNull();
});

it('verifies an extend payment and extends the subscription period', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant('active');
    $plan = c25Plan('Verify Extend', 99000);
    $subscription = c25Subscription($tenant, $plan);
    $originalEnd = now()->addDays(20);
    $subscription->update(['current_period_ends_at' => $originalEnd]);
    $charge = c25Charge($admin, $tenant, $plan, SubscriptionPaymentIntent::ExtendSubscription);
    $payment = app(SubscriptionPaymentService::class)->record($charge, 'TRX-VE-001', $admin, extensionDays: 30);

    app(SubscriptionPaymentService::class)->verify($payment, $admin);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->fresh()->current_period_ends_at->toDateString())->toBe($originalEnd->copy()->addDays(30)->toDateString())
        ->and($charge->fresh()->status)->toBe(SubscriptionChargeStatus::Paid);

    $event = c25Events($tenant)->firstWhere('type', SubscriptionEventType::Renewed);
    expect($event)->not->toBeNull()
        ->and($event->actor_user_id)->toBe($admin->id);
});

it('routes an expired same-plan renewal through the reactivation path', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Verify Reactivate', 99000);
    $subscription = c25Subscription($tenant, $plan);
    $subscription->update(['status' => SubscriptionStatus::Expired, 'current_period_ends_at' => now()->subDay()]);
    $charge = c25Charge($admin, $tenant, $plan, SubscriptionPaymentIntent::ExtendSubscription);
    $payment = app(SubscriptionPaymentService::class)->record($charge, 'TRX-VR-001', $admin, extensionDays: 30);

    app(SubscriptionPaymentService::class)->verify($payment, $admin);

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->fresh()->current_period_ends_at->isFuture())->toBeTrue()
        ->and($subscription->fresh()->cancelled_at)->toBeNull()
        ->and($tenant->refresh()->status)->toBe('active');

    expect(c25Events($tenant)->firstWhere('type', SubscriptionEventType::Reactivated))->not->toBeNull();
});

it('keeps the payment pending when the plan is no longer active', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Plan Inactive', 99000);
    $charge = c25Charge($admin, $tenant, $plan);
    $payment = app(SubscriptionPaymentService::class)->record($charge, 'TRX-PI-001', $admin);
    $plan->update(['is_active' => false]);

    expect(fn () => app(SubscriptionPaymentService::class)->verify($payment, $admin))
        ->toThrow(DomainException::class, 'no longer available');

    expect($payment->fresh()->status)->toBe(SubscriptionPaymentStatus::Pending)
        ->and(c25Events($tenant)->count())->toBe(0);
});

it('does not transition an already verified or rejected payment', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Terminal', 99000);

    $verifiedPayment = app(SubscriptionPaymentService::class)->record(
        c25Charge($admin, $tenant, $plan),
        'TRX-TERM-001',
        $admin,
    );
    app(SubscriptionPaymentService::class)->verify($verifiedPayment, $admin);

    expect(fn () => app(SubscriptionPaymentService::class)->verify($verifiedPayment->fresh(), $admin))
        ->toThrow(DomainException::class, 'Only pending');
    expect(fn () => app(SubscriptionPaymentService::class)->reject($verifiedPayment->fresh(), $admin, 'late'))
        ->toThrow(DomainException::class, 'Only pending');

    $rejectedPayment = app(SubscriptionPaymentService::class)->record(
        c25Charge($admin, $tenant, $plan),
        'TRX-TERM-002',
        $admin,
    );
    app(SubscriptionPaymentService::class)->reject($rejectedPayment, $admin, 'No funds.');

    expect(fn () => app(SubscriptionPaymentService::class)->verify($rejectedPayment->fresh(), $admin))
        ->toThrow(DomainException::class, 'Only pending');
    expect(fn () => app(SubscriptionPaymentService::class)->reject($rejectedPayment->fresh(), $admin, 'again'))
        ->toThrow(DomainException::class, 'Only pending');

    expect(c25Events($tenant)->count())->toBe(1);
});

it('requires a rejection reason', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Reject Reason', 99000);
    $charge = c25Charge($admin, $tenant, $plan);
    $payment = app(SubscriptionPaymentService::class)->record($charge, 'TRX-REASON-001', $admin);

    expect(fn () => app(SubscriptionPaymentService::class)->reject($payment, $admin, '   '))
        ->toThrow(DomainException::class, 'reason');

    expect($payment->fresh()->status)->toBe(SubscriptionPaymentStatus::Pending);
});

it('rejecting a payment does not mutate the subscription', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant('active');
    $plan = c25Plan('Reject No Mutate', 99000);
    $subscription = c25Subscription($tenant, $plan);
    $charge = c25Charge($admin, $tenant, $plan, SubscriptionPaymentIntent::ExtendSubscription);
    $payment = app(SubscriptionPaymentService::class)->record($charge, 'TRX-REJ-001', $admin, extensionDays: 30);

    $rejected = app(SubscriptionPaymentService::class)->reject($payment, $admin, 'Funds not received.');

    expect($rejected->status)->toBe(SubscriptionPaymentStatus::Rejected)
        ->and($rejected->rejected_by)->toBe($admin->id)
        ->and($rejected->rejected_at)->not->toBeNull()
        ->and($rejected->rejected_reason)->toBe('Funds not received.');

    expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active)
        ->and(c25Events($tenant)->count())->toBe(0);
});

it('requires an active platform admin for payment operations', function (): void {
    $tenant = c25Tenant();
    $plan = c25Plan('Guard Pay', 99000);
    $charge = c25Charge(c25Admin(), $tenant, $plan);
    $staff = User::factory()->create(['is_platform_admin' => false, 'role' => 'staff']);
    $inactiveAdmin = User::factory()->create(['is_platform_admin' => true, 'is_active' => false, 'app_authentication_secret' => 'test-secret']);

    foreach ([$staff, $inactiveAdmin] as $actor) {
        expect(fn () => app(SubscriptionPaymentService::class)->record($charge, 'TRX-GUARD-'.Str::random(6), $actor))
            ->toThrow(DomainException::class, 'Platform Admin');
    }

    expect(SubscriptionPayment::query()->count())->toBe(0);
});

it('rejects payment operations in Dedicated mode', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Dedicated Pay', 99000);
    $charge = c25Charge($admin, $tenant, $plan);
    config()->set('deployment.mode', DeploymentMode::Dedicated->value);

    expect(fn () => app(SubscriptionPaymentService::class)->record($charge, 'TRX-DED-001', $admin))
        ->toThrow(DomainException::class, 'Platform Admin');

    expect(SubscriptionPayment::query()->count())->toBe(0);
});

it('rolls back payment verification when the subscription write fails', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Rollback Pay', 99000);
    c25Subscription($tenant, $plan);
    $charge = c25Charge($admin, $tenant, $plan);
    $payment = app(SubscriptionPaymentService::class)->record($charge, 'TRX-RB-001', $admin);

    expect(fn () => app(SubscriptionPaymentService::class)->verify($payment, $admin))
        ->toThrow(DomainException::class, 'already assigned');

    expect($payment->fresh()->status)->toBe(SubscriptionPaymentStatus::Pending)
        ->and($payment->fresh()->verified_by)->toBeNull()
        ->and($payment->fresh()->received_at)->toBeNull()
        ->and($charge->fresh()->status)->toBe(SubscriptionChargeStatus::Open)
        ->and($charge->fresh()->paid_amount)->toBe(0)
        ->and(c25Events($tenant)->count())->toBe(0);
});

it('links the payment reference and id into the subscription event audit trail', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Link Pay', 99000);
    $charge = c25Charge($admin, $tenant, $plan);
    $payment = app(SubscriptionPaymentService::class)->record($charge, 'TRX-LINK-001', $admin);

    app(SubscriptionPaymentService::class)->verify($payment, $admin);

    $event = c25Events($tenant)->first();
    expect($event)->not->toBeNull()
        ->and($event->type)->toBe(SubscriptionEventType::Subscribed)
        ->and($event->actor_user_id)->toBe($admin->id)
        ->and($event->effective_at)->not->toBeNull()
        ->and($event->note)->toContain('#'.$payment->id)
        ->and($event->note)->toContain('#'.$charge->id)
        ->and($event->note)->toContain('TRX-LINK-001');
});

it('backfills verified payments into paid charges', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Backfill Pay', 99000);
    c25Subscription($tenant, $plan);
    $payment = SubscriptionPayment::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'intent' => SubscriptionPaymentIntent::AssignPlan,
        'status' => SubscriptionPaymentStatus::Verified,
        'provider' => 'manual',
        'payment_method' => 'bkash',
        'currency' => 'BDT',
        'amount' => 99000,
        'reference' => 'TRX-LEGACY-001',
        'received_at' => now()->subDay(),
        'verified_by' => $admin->id,
        'created_by' => $admin->id,
    ]);

    $count = SubscriptionChargeService::backfillVerifiedPayments();

    expect($count)->toBe(1);

    $charge = SubscriptionCharge::query()->first();
    expect($charge)->not->toBeNull()
        ->and($charge->tenant_id)->toBe($tenant->id)
        ->and($charge->plan_id)->toBe($plan->id)
        ->and($charge->intent)->toBe(SubscriptionPaymentIntent::AssignPlan)
        ->and($charge->net_amount)->toBe(99000)
        ->and($charge->paid_amount)->toBe(99000)
        ->and($charge->status)->toBe(SubscriptionChargeStatus::Paid)
        ->and($payment->fresh()->subscription_charge_id)->toBe($charge->id);
});

it('leaves already-linked payments untouched when backfilling again', function (): void {
    $admin = c25Admin();
    $tenant = c25Tenant();
    $plan = c25Plan('Backfill Idempotent', 99000);
    SubscriptionPayment::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'intent' => SubscriptionPaymentIntent::AssignPlan,
        'status' => SubscriptionPaymentStatus::Verified,
        'provider' => 'manual',
        'payment_method' => 'bkash',
        'currency' => 'BDT',
        'amount' => 99000,
        'reference' => 'TRX-LEGACY-002',
        'received_at' => now()->subDay(),
        'verified_by' => $admin->id,
        'created_by' => $admin->id,
    ]);

    SubscriptionChargeService::backfillVerifiedPayments();

    $second = SubscriptionChargeService::backfillVerifiedPayments();

    expect($second)->toBe(0)
        ->and(SubscriptionCharge::query()->count())->toBe(1);
});
