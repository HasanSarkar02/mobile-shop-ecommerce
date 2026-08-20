<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionChargeStatus;
use App\Enums\SubscriptionStatus;
use App\Filament\Platform\Support\PlatformMoney;
use App\Models\Plan;
use App\Models\SubscriptionCharge;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Notifications\NotificationRecipient;
use App\Support\Tenancy\Tenancy;
use App\Support\Tenancy\TenantUrlGenerator;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Charge-driven due / overdue email reminders. Evaluates each eligible
 * SubscriptionCharge exactly once per cadence (7d/3d/1d/due/overdue) and hands
 * delivery to NotificationService — idempotency is enforced by its log table's
 * (tenant_id, idempotency_key) unique constraint, so repeated runs never
 * duplicate a reminder. The command never mutates charges or subscriptions.
 */
class SubscriptionReminderService
{
    private const CADENCE_EVENTS = [
        '7d' => 'subscription.charge.reminder.7d',
        '3d' => 'subscription.charge.reminder.3d',
        '1d' => 'subscription.charge.reminder.1d',
        'due' => 'subscription.charge.reminder.due',
        'overdue' => 'subscription.charge.reminder.overdue',
    ];

    private const ELIGIBLE_SUBSCRIPTION_STATUSES = [
        SubscriptionStatus::Active,
        SubscriptionStatus::Trialing,
        SubscriptionStatus::PastDue,
    ];

    public function __construct(
        private readonly NotificationService $notifications,
        private readonly Tenancy $tenancy,
        private readonly TenantUrlGenerator $urls,
    ) {}

    /**
     * Process all eligible charges for the given business date. Returns the
     * number of reminder notifications dispatched (counted per charge, before
     * idempotency filtering).
     *
     * @param  CarbonInterface|null  $now  Business date boundary; defaults to the
     *                                     configured application timezone.
     */
    public function process(?CarbonInterface $now = null): int
    {
        $now ??= Carbon::now((string) config('app.timezone', 'UTC'));

        $dispatched = 0;

        SubscriptionCharge::query()
            ->whereIn('status', [
                SubscriptionChargeStatus::Open->value,
                SubscriptionChargeStatus::PartiallyPaid->value,
            ])
            ->whereNotNull('period_ends_at')
            ->with(['tenant', 'plan'])
            ->chunkById(100, function (Collection $charges) use ($now, &$dispatched): void {
                $subscriptions = $this->eligibleSubscriptions($charges);

                foreach ($charges as $charge) {
                    if ($this->dispatchForCharge($charge, $subscriptions, $now)) {
                        $dispatched++;
                    }
                }
            });

        return $dispatched;
    }

    /**
     * @param  Collection<int, SubscriptionCharge>  $charges
     * @return array<int, TenantSubscription> keyed by tenant id
     */
    private function eligibleSubscriptions(Collection $charges): array
    {
        $tenantIds = $charges
            ->map(fn (SubscriptionCharge $charge): int => (int) $charge->tenant_id)
            ->unique()
            ->values();

        if ($tenantIds->isEmpty()) {
            return [];
        }

        return TenantSubscription::query()
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('status', array_map(
                fn (SubscriptionStatus $status): string => $status->value,
                self::ELIGIBLE_SUBSCRIPTION_STATUSES,
            ))
            ->get()
            ->keyBy('tenant_id')
            ->all();
    }

    private function dispatchForCharge(
        SubscriptionCharge $charge,
        array $subscriptions,
        CarbonInterface $now,
    ): bool {
        $cadence = $this->cadenceFor($charge, $now);

        if ($cadence === null || $charge->outstandingAmount() <= 0) {
            return false;
        }

        $subscription = $subscriptions[(int) $charge->tenant_id] ?? null;

        if (! $subscription instanceof TenantSubscription) {
            return false;
        }

        $status = $subscription->getAttribute('status');

        if (! $status instanceof SubscriptionStatus
            || ! in_array($status, self::ELIGIBLE_SUBSCRIPTION_STATUSES, true)) {
            return false;
        }

        $tenant = $charge->tenant instanceof Tenant
            ? $charge->tenant
            : Tenant::query()->find((int) $charge->tenant_id);

        if (! $tenant instanceof Tenant) {
            return false;
        }

        $owner = $this->activeOwner($tenant);

        if ($owner === null) {
            return false;
        }

        $this->tenancy->set($tenant);

        try {
            $this->notifications->send(
                self::CADENCE_EVENTS[$cadence],
                new NotificationRecipient(
                    audience: 'owner',
                    modelType: User::class,
                    modelId: (int) $owner->id,
                    addresses: ['email' => (string) $owner->email],
                ),
                $this->contextFor($charge, $tenant, $owner),
            );
        } catch (Throwable $exception) {
            report($exception);

            return false;
        } finally {
            $this->tenancy->set(null);
        }

        return true;
    }

    private function cadenceFor(SubscriptionCharge $charge, CarbonInterface $now): ?string
    {
        $periodEndsAt = $charge->getAttribute('period_ends_at');

        if (! $periodEndsAt instanceof CarbonInterface) {
            return null;
        }

        $today = $now->copy()->startOfDay();
        $dueDate = $periodEndsAt->copy()->startOfDay();

        if ($today->equalTo($dueDate->copy()->subDays(7))) {
            return '7d';
        }

        if ($today->equalTo($dueDate->copy()->subDays(3))) {
            return '3d';
        }

        if ($today->equalTo($dueDate->copy()->subDay())) {
            return '1d';
        }

        if ($today->equalTo($dueDate)) {
            return 'due';
        }

        if ($today->greaterThan($dueDate)) {
            return 'overdue';
        }

        return null;
    }

    private function activeOwner(Tenant $tenant): ?User
    {
        return User::query()
            ->where('tenant_id', $tenant->id)
            ->where('role', 'owner')
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->first();
    }

    /** @return array<string, mixed> */
    private function contextFor(SubscriptionCharge $charge, Tenant $tenant, User $owner): array
    {
        $dueDate = $charge->getAttribute('period_ends_at');
        $timezone = (string) config('app.timezone', 'UTC');
        $status = $charge->getAttribute('status');
        $plan = $charge->plan;

        return [
            'store' => ['name' => $tenant->name],
            'owner' => ['name' => $owner->name],
            'plan' => ['name' => $plan instanceof Plan ? $plan->name : 'current plan'],
            'charge' => [
                'outstanding' => PlatformMoney::format($charge->outstandingAmount()),
                'currency' => 'BDT',
                'due_date' => $dueDate instanceof CarbonInterface
                    ? $dueDate->copy()->tz($timezone)->format('M j, Y')
                    : 'soon',
                'status' => $status instanceof SubscriptionChargeStatus
                    ? $status->label()
                    : (string) $status,
            ],
            'billing' => ['url' => $this->urls->canonicalPath($tenant, '/admin/billing')],
            'related_type' => SubscriptionCharge::class,
            'related_id' => (string) $charge->getKey(),
        ];
    }
}
