<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Enums\NotificationStatus;
use App\Enums\SubscriptionChargeStatus;
use App\Enums\SubscriptionPaymentIntent;
use App\Enums\SubscriptionStatus;
use App\Jobs\SendNotificationJob;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Plan;
use App\Models\SubscriptionCharge;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Notifications\NotificationRecipient;
use App\Services\NotificationService;
use App\Services\SubscriptionReminderService;
use App\Support\ReminderTemplateDefaults;
use App\Support\Tenancy\Tenancy;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('deployment.mode', DeploymentMode::SaaS->value);
    app(Tenancy::class)->set(null);
    Carbon::setTestNow(Carbon::parse('2026-08-19 10:00:00', 'UTC'));
    Queue::fake();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function c7Plan(string $prefix, int $price): Plan
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

function c7Tenant(): Tenant
{
    return Tenant::factory()->create([
        'subdomain' => 'c7-'.Str::lower(Str::random(8)),
        'status' => 'active',
    ]);
}

function c7Subscription(Tenant $tenant, Plan $plan, string $status = 'active'): TenantSubscription
{
    return TenantSubscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::from($status),
        'current_period_starts_at' => now()->subMonth(),
        'current_period_ends_at' => now()->addMonth(),
        'plan_name' => $plan->name,
        'billing_period' => $plan->billing_period,
        'price' => $plan->price,
        'max_products' => $plan->max_products,
        'max_staff' => $plan->max_staff,
        'custom_domain_allowed' => $plan->custom_domain_allowed,
    ]);
}

function c7Owner(Tenant $tenant, bool $active = true, ?string $email = null): User
{
    return User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
        'is_active' => $active,
        'email' => $email ?? 'owner-'.Str::lower(Str::random(8)).'@example.test',
    ]);
}

function c7Templates(Tenant $tenant): void
{
    $tenancy = app(Tenancy::class);
    $tenancy->set($tenant);

    try {
        foreach (ReminderTemplateDefaults::definitions() as $definition) {
            NotificationTemplate::query()->firstOrCreate(
                ['event_key' => $definition['event_key'], 'channel' => $definition['channel']],
                ['subject' => $definition['subject'], 'body' => $definition['body'], 'is_active' => true],
            );
        }
    } finally {
        $tenancy->set(null);
    }
}

function c7Charge(
    Tenant $tenant,
    Plan $plan,
    CarbonInterface $periodEndsAt,
    int $net = 1000,
    int $paid = 0,
    string $status = 'open',
): SubscriptionCharge {
    return SubscriptionCharge::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'intent' => SubscriptionPaymentIntent::AssignPlan,
        'period_starts_at' => $periodEndsAt->copy()->subMonth(),
        'period_ends_at' => $periodEndsAt,
        'base_amount' => $net,
        'discount_amount' => 0,
        'net_amount' => $net,
        'paid_amount' => $paid,
        'status' => SubscriptionChargeStatus::from($status),
    ]);
}

function c7Run(): int
{
    return app(SubscriptionReminderService::class)->process();
}

function c7Logs(Tenant $tenant): Collection
{
    return NotificationLog::query()
        ->withoutGlobalScope('tenant')
        ->where('tenant_id', $tenant->id)
        ->get();
}

it('sends a 7-day-before reminder for an open charge', function (): void {
    $tenant = c7Tenant();
    $plan = c7Plan('C7 SevenDay', 1000);
    c7Subscription($tenant, $plan);
    c7Owner($tenant);
    c7Templates($tenant);
    $charge = c7Charge($tenant, $plan, now()->addDays(7));

    $dispatched = c7Run();

    expect($dispatched)->toBe(1)
        ->and(c7Logs($tenant)->first()->event_key)->toBe('subscription.charge.reminder.7d');
});

it('sends a 3-day-before reminder', function (): void {
    $tenant = c7Tenant();
    $plan = c7Plan('C7 ThreeDay', 1000);
    c7Subscription($tenant, $plan);
    c7Owner($tenant);
    c7Templates($tenant);
    $charge = c7Charge($tenant, $plan, now()->addDays(3));

    $dispatched = c7Run();

    expect($dispatched)->toBe(1)
        ->and(c7Logs($tenant)->first()->event_key)->toBe('subscription.charge.reminder.3d');
});

it('sends a 1-day-before reminder', function (): void {
    $tenant = c7Tenant();
    $plan = c7Plan('C7 OneDay', 1000);
    c7Subscription($tenant, $plan);
    c7Owner($tenant);
    c7Templates($tenant);
    $charge = c7Charge($tenant, $plan, now()->addDay());

    $dispatched = c7Run();

    expect($dispatched)->toBe(1)
        ->and(c7Logs($tenant)->first()->event_key)->toBe('subscription.charge.reminder.1d');
});

it('sends a due-day reminder', function (): void {
    $tenant = c7Tenant();
    $plan = c7Plan('C7 DueDay', 1000);
    c7Subscription($tenant, $plan);
    c7Owner($tenant);
    c7Templates($tenant);
    $charge = c7Charge($tenant, $plan, now());

    $dispatched = c7Run();

    expect($dispatched)->toBe(1)
        ->and(c7Logs($tenant)->first()->event_key)->toBe('subscription.charge.reminder.due');
});

it('sends an overdue reminder', function (): void {
    $tenant = c7Tenant();
    $plan = c7Plan('C7 Overdue', 1000);
    c7Subscription($tenant, $plan);
    c7Owner($tenant);
    c7Templates($tenant);
    $charge = c7Charge($tenant, $plan, now()->subDay());

    $dispatched = c7Run();

    expect($dispatched)->toBe(1)
        ->and(c7Logs($tenant)->first()->event_key)->toBe('subscription.charge.reminder.overdue');
});

it('dispatches a reminder exactly once when run repeatedly', function (): void {
    $tenant = c7Tenant();
    $plan = c7Plan('C7 Idempotent', 1000);
    c7Subscription($tenant, $plan);
    c7Owner($tenant);
    c7Templates($tenant);
    $charge = c7Charge($tenant, $plan, now()->addDays(7));

    c7Run();
    c7Run();

    expect(c7Logs($tenant))->toHaveCount(1);
});

it('sends the overdue reminder exactly once even when run again later', function (): void {
    $tenant = c7Tenant();
    $plan = c7Plan('C7 OverdueOnce', 1000);
    c7Subscription($tenant, $plan);
    c7Owner($tenant);
    c7Templates($tenant);
    $charge = c7Charge($tenant, $plan, now()->subDay());

    c7Run();
    Carbon::setTestNow(Carbon::parse('2026-08-25 10:00:00', 'UTC'));
    c7Run();

    expect(c7Logs($tenant))->toHaveCount(1)
        ->and(c7Logs($tenant)->first()->event_key)->toBe('subscription.charge.reminder.overdue');
});

it('uses the outstanding amount for partially paid charges', function (): void {
    $tenant = c7Tenant();
    $plan = c7Plan('C7 Partial', 1000);
    c7Subscription($tenant, $plan);
    c7Owner($tenant);
    c7Templates($tenant);
    $charge = c7Charge($tenant, $plan, now()->addDays(7), net: 2000, paid: 1000, status: 'partially_paid');

    c7Run();

    expect($charge->outstandingAmount())->toBe(1000)
        ->and(c7Logs($tenant)->first()->body_rendered)->toContain('৳10.00');
});

it('skips fully paid charges', function (): void {
    $tenant = c7Tenant();
    $plan = c7Plan('C7 Paid', 1000);
    c7Subscription($tenant, $plan);
    c7Owner($tenant);
    c7Templates($tenant);
    $charge = c7Charge($tenant, $plan, now(), status: 'paid');

    expect(c7Run())->toBe(0)
        ->and(c7Logs($tenant))->toHaveCount(0);
});

it('skips void charges', function (): void {
    $tenant = c7Tenant();
    $plan = c7Plan('C7 Void', 1000);
    c7Subscription($tenant, $plan);
    c7Owner($tenant);
    c7Templates($tenant);
    $charge = c7Charge($tenant, $plan, now(), status: 'void');

    expect(c7Run())->toBe(0)
        ->and(c7Logs($tenant))->toHaveCount(0);
});

it('skips charges with no outstanding balance', function (): void {
    $tenant = c7Tenant();
    $plan = c7Plan('C7 Zero', 1000);
    c7Subscription($tenant, $plan);
    c7Owner($tenant);
    c7Templates($tenant);
    $charge = c7Charge($tenant, $plan, now(), net: 1000, paid: 1000);

    expect(c7Run())->toBe(0)
        ->and(c7Logs($tenant))->toHaveCount(0);
});

it('skips charges for cancelled subscriptions', function (): void {
    $tenant = c7Tenant();
    $plan = c7Plan('C7 Cancelled', 1000);
    c7Subscription($tenant, $plan, status: 'cancelled');
    c7Owner($tenant);
    c7Templates($tenant);
    $charge = c7Charge($tenant, $plan, now());

    expect(c7Run())->toBe(0)
        ->and(c7Logs($tenant))->toHaveCount(0);
});

it('skips charges for expired subscriptions', function (): void {
    $tenant = c7Tenant();
    $plan = c7Plan('C7 Expired', 1000);
    c7Subscription($tenant, $plan, status: 'expired');
    c7Owner($tenant);
    c7Templates($tenant);
    $charge = c7Charge($tenant, $plan, now());

    expect(c7Run())->toBe(0)
        ->and(c7Logs($tenant))->toHaveCount(0);
});

it('skips charges without a period end date', function (): void {
    $tenant = c7Tenant();
    $plan = c7Plan('C7 NoPeriodEnd', 1000);
    c7Subscription($tenant, $plan);
    c7Owner($tenant);
    c7Templates($tenant);
    $charge = c7Charge($tenant, $plan, now());
    $charge->forceFill(['period_ends_at' => null])->save();

    expect(c7Run())->toBe(0)
        ->and(c7Logs($tenant))->toHaveCount(0);
});

it('skips charges without an active owner and continues the batch', function (): void {
    $withOwner = c7Tenant();
    $withoutOwner = c7Tenant();
    $planA = c7Plan('C7 BatchA', 1000);
    $planB = c7Plan('C7 BatchB', 1000);
    c7Subscription($withOwner, $planA);
    c7Subscription($withoutOwner, $planB);
    c7Owner($withOwner);
    c7Templates($withOwner);
    c7Templates($withoutOwner);
    c7Charge($withOwner, $planA, now());
    c7Charge($withoutOwner, $planB, now());

    $dispatched = c7Run();

    expect($dispatched)->toBe(1)
        ->and(c7Logs($withoutOwner))->toHaveCount(0)
        ->and(c7Logs($withOwner))->toHaveCount(1);
});

it('continues the batch when an individual send fails', function (): void {
    $tenantA = c7Tenant();
    $tenantB = c7Tenant();
    $planA = c7Plan('C7 FailA', 1000);
    $planB = c7Plan('C7 FailB', 1000);
    c7Subscription($tenantA, $planA);
    c7Subscription($tenantB, $planB);
    c7Owner($tenantA);
    c7Owner($tenantB);
    c7Templates($tenantA);
    c7Templates($tenantB);
    c7Charge($tenantA, $planA, now()->addDays(7));
    c7Charge($tenantB, $planB, now());

    $inner = app(NotificationService::class);
    $throwing = new class($inner) extends NotificationService
    {
        public function __construct(private readonly NotificationService $inner) {}

        public function send(string $eventKey, NotificationRecipient $recipient, array $context = []): void
        {
            if ($eventKey === 'subscription.charge.reminder.due') {
                throw new RuntimeException('Simulated send failure.');
            }

            $this->inner->send($eventKey, $recipient, $context);
        }
    };
    app()->instance(NotificationService::class, $throwing);

    $dispatched = c7Run();

    expect($dispatched)->toBe(1)
        ->and(c7Logs($tenantA))->toHaveCount(1)
        ->and(c7Logs($tenantB))->toHaveCount(0);
});

it('records the related charge, recipient and transport job', function (): void {
    $tenant = c7Tenant();
    $plan = c7Plan('C7 Related', 1000);
    c7Subscription($tenant, $plan);
    $owner = c7Owner($tenant);
    c7Templates($tenant);
    $charge = c7Charge($tenant, $plan, now());

    c7Run();

    $log = c7Logs($tenant)->first();
    expect($log->related_type)->toBe(SubscriptionCharge::class)
        ->and($log->related_id)->toBe($charge->id)
        ->and($log->recipient_type)->toBe(User::class)
        ->and($log->recipient_id)->toBe($owner->id)
        ->and($log->recipient_address)->toBe($owner->email)
        ->and($log->status)->toBe(NotificationStatus::Queued);

    Queue::assertPushed(SendNotificationJob::class);
});

it('renders the expected template variables', function (): void {
    $tenant = c7Tenant();
    $plan = c7Plan('C7 Render', 1000);
    c7Subscription($tenant, $plan);
    $owner = c7Owner($tenant);
    c7Templates($tenant);
    $charge = c7Charge($tenant, $plan, now()->addDays(7));

    c7Run();

    $log = c7Logs($tenant)->first();
    expect($log->subject_rendered)->toContain($tenant->name)
        ->and($log->body_rendered)->toContain($owner->name)
        ->and($log->body_rendered)->toContain($plan->name)
        ->and($log->body_rendered)->toContain('৳10.00')
        ->and($log->body_rendered)->toContain('BDT')
        ->and($log->body_rendered)->toContain('Aug 26, 2026')
        ->and($log->body_rendered)->toContain('/admin/billing');
});

it('only fires the cadence that matches today', function (): void {
    $tenant = c7Tenant();
    $plan = c7Plan('C7 OffCadence', 1000);
    c7Subscription($tenant, $plan);
    c7Owner($tenant);
    c7Templates($tenant);
    $charge = c7Charge($tenant, $plan, now()->addDays(5));

    expect(c7Run())->toBe(0)
        ->and(c7Logs($tenant))->toHaveCount(0);
});

it('registers the reminders command', function (): void {
    expect(Artisan::all())->toHaveKey('subscriptions:process-reminders');
});

it('registers the reminders schedule at 00:10 with overlap protection', function (): void {
    $contents = file_get_contents(base_path('routes/console.php'));

    expect($contents)->toContain('ProcessSubscriptionReminders')
        ->and($contents)->toContain("dailyAt('00:10')")
        ->and($contents)->toContain('withoutOverlapping()')
        ->and($contents)->toContain('onOneServer()');
});

it('deduplicates an identical direct send through NotificationService', function (): void {
    $tenant = c7Tenant();
    $plan = c7Plan('C7 DirectDedupe', 1000);
    c7Subscription($tenant, $plan);
    $owner = c7Owner($tenant);
    c7Templates($tenant);
    $charge = c7Charge($tenant, $plan, now());

    $tenancy = app(Tenancy::class);
    $tenancy->set($tenant);

    try {
        $recipient = new NotificationRecipient(
            audience: 'owner',
            modelType: User::class,
            modelId: (int) $owner->id,
            addresses: ['email' => (string) $owner->email],
        );
        $context = [
            'related_type' => SubscriptionCharge::class,
            'related_id' => (string) $charge->id,
        ];

        app(NotificationService::class)->send('subscription.charge.reminder.due', $recipient, $context);
        app(NotificationService::class)->send('subscription.charge.reminder.due', $recipient, $context);
    } finally {
        $tenancy->set(null);
    }

    expect(c7Logs($tenant))->toHaveCount(1);
});
