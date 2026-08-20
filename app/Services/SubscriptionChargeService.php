<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionChargeStatus;
use App\Enums\SubscriptionDiscountType;
use App\Enums\SubscriptionPaymentIntent;
use App\Enums\SubscriptionPaymentStatus;
use App\Models\Plan;
use App\Models\SubscriptionCharge;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Sole writer for SubscriptionCharge creation. A charge snapshots the billing
 * amounts (base / discount / net) at creation and is frozen afterwards: later
 * Plan price changes never alter an existing charge. Payment allocation,
 * partial payments and settlement belong to Phase 2.
 */
class SubscriptionChargeService
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function createCharge(
        Tenant $tenant,
        Plan $plan,
        SubscriptionPaymentIntent $intent,
        User $actor,
        ?Carbon $periodStartsAt = null,
        ?Carbon $periodEndsAt = null,
        ?int $baseAmount = null,
        ?SubscriptionDiscountType $discountType = null,
        ?int $discountValue = null,
        ?string $note = null,
    ): SubscriptionCharge {
        return DB::transaction(function () use ($tenant, $plan, $intent, $actor, $periodStartsAt, $periodEndsAt, $baseAmount, $discountType, $discountValue, $note): SubscriptionCharge {
            $this->subscriptions->assertCanManageSubscriptions($actor);

            if (! $plan->exists || ! $plan->is_active) {
                throw new DomainException('The requested plan is not available.');
            }

            Tenant::query()->whereKey($tenant->id)->lockForUpdate()->first();

            $this->assertNoOpenCharge($tenant, $intent);

            $base = $baseAmount ?? $plan->price;

            if ($base <= 0) {
                throw new DomainException('The charge base amount must be a positive number.');
            }

            $discountAmount = $this->resolveDiscountAmount($base, $discountType, $discountValue);

            $net = $base - $discountAmount;

            if ($net <= 0) {
                throw new DomainException('The charge net amount must be greater than zero.');
            }

            $charge = SubscriptionCharge::query()->create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'intent' => $intent,
                'period_starts_at' => $periodStartsAt,
                'period_ends_at' => $periodEndsAt,
                'base_amount' => $base,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $discountAmount,
                'net_amount' => $net,
                'paid_amount' => 0,
                'status' => SubscriptionChargeStatus::Open,
                'note' => $note,
                'created_by' => $actor->id,
            ]);

            $this->log($charge, $actor, $intent, $discountType);

            return $charge;
        });
    }

    /**
     * Link every verified payment that predates charge allocation to a paid
     * charge. Idempotent: payments already linked, and payments that are not
     * verified, are left untouched. Charge amounts mirror the received payment
     * (net = paid = amount), so the money actually taken is never rewritten.
     */
    public static function backfillVerifiedPayments(): int
    {
        $linked = 0;

        $payments = SubscriptionPayment::query()
            ->where('status', SubscriptionPaymentStatus::Verified->value)
            ->whereNull('subscription_charge_id')
            ->get();

        foreach ($payments as $payment) {
            $linked += (int) DB::transaction(function () use ($payment): bool {
                $locked = SubscriptionPayment::query()
                    ->whereKey($payment->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($locked === null
                    || $locked->getAttribute('status') !== SubscriptionPaymentStatus::Verified
                    || $locked->subscription_charge_id !== null) {
                    return false;
                }

                $tenantId = (int) $locked->tenant_id;
                $planId = $locked->plan_id !== null ? (int) $locked->plan_id : null;
                $rawIntent = $locked->getAttribute('intent');
                $intent = $rawIntent instanceof SubscriptionPaymentIntent
                    ? $rawIntent
                    : SubscriptionPaymentIntent::from((string) $rawIntent);

                $subscription = TenantSubscription::query()
                    ->where('tenant_id', $tenantId)
                    ->where('plan_id', $planId)
                    ->orderByDesc('current_period_ends_at')
                    ->first();

                $amount = (int) $locked->amount;
                $verifiedBy = $locked->verified_by !== null ? (int) $locked->verified_by : null;

                $charge = SubscriptionCharge::query()->create([
                    'tenant_id' => $tenantId,
                    'plan_id' => $planId,
                    'intent' => $intent,
                    'period_starts_at' => $subscription?->current_period_starts_at,
                    'period_ends_at' => $subscription?->current_period_ends_at,
                    'base_amount' => $amount,
                    'discount_type' => null,
                    'discount_value' => null,
                    'discount_amount' => 0,
                    'net_amount' => $amount,
                    'paid_amount' => $amount,
                    'status' => SubscriptionChargeStatus::Paid,
                    'reference' => (string) $locked->reference,
                    'note' => $locked->note,
                    'created_by' => $verifiedBy,
                ]);

                $locked->update(['subscription_charge_id' => $charge->id]);

                return true;
            });
        }

        return $linked;
    }

    private function resolveDiscountAmount(int $base, ?SubscriptionDiscountType $type, ?int $value): int
    {
        if ($type === null || $value === null) {
            return 0;
        }

        if ($value <= 0) {
            throw new DomainException('The discount value must be a positive number.');
        }

        $amount = match ($type) {
            SubscriptionDiscountType::Percentage => $this->resolvePercentageDiscount($base, $value),
            SubscriptionDiscountType::Fixed => min($value, $base),
        };

        if ($amount >= $base) {
            throw new DomainException('The discount cannot equal or exceed the base amount.');
        }

        return $amount;
    }

    private function resolvePercentageDiscount(int $base, int $percentage): int
    {
        if ($percentage > 100) {
            throw new DomainException('The percentage discount cannot exceed 100.');
        }

        return intdiv($base * $percentage, 100);
    }

    private function assertNoOpenCharge(Tenant $tenant, SubscriptionPaymentIntent $intent): void
    {
        $exists = SubscriptionCharge::query()
            ->where('tenant_id', $tenant->id)
            ->where('intent', $intent->value)
            ->whereIn('status', [
                SubscriptionChargeStatus::Open->value,
                SubscriptionChargeStatus::PartiallyPaid->value,
            ])
            ->exists();

        if ($exists) {
            throw new DomainException('An open or partially paid charge already exists for this tenant and intent.');
        }
    }

    private function log(
        SubscriptionCharge $charge,
        User $actor,
        SubscriptionPaymentIntent $intent,
        ?SubscriptionDiscountType $discountType,
    ): void {
        activity('subscription-charges')
            ->performedOn($charge)
            ->causedBy($actor)
            ->event('subscription-charge.created')
            ->withProperties([
                'tenant_id' => $charge->tenant_id,
                'plan_id' => $charge->plan_id,
                'intent' => $intent->value,
                'base_amount' => $charge->base_amount,
                'discount_type' => $discountType?->value,
                'discount_value' => $charge->discount_value,
                'discount_amount' => $charge->discount_amount,
                'net_amount' => $charge->net_amount,
            ])
            ->log('subscription-charge.created');
    }
}
