<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionChargeStatus;
use App\Enums\SubscriptionPaymentIntent;
use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\SubscriptionCharge;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Sole writer for the SubscriptionPayment lifecycle. Payments are recorded
 * against an open SubscriptionCharge (the billing obligation) and, once
 * verified, are allocated to that charge. Only when a charge becomes fully
 * paid does the payment resolve into a SubscriptionService operation, which
 * remains the only writer of TenantSubscription state.
 */
class SubscriptionPaymentService
{
    public const MANUAL_PROVIDER = 'manual';

    public const MANUAL_METHODS = ['bkash', 'nagad', 'rocket', 'other'];

    public const SUPPORTED_CURRENCY = 'BDT';

    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function record(
        SubscriptionCharge $charge,
        string $reference,
        User $actor,
        string $paymentMethod = 'other',
        ?int $amount = null,
        ?int $extensionDays = null,
        ?string $note = null,
    ): SubscriptionPayment {
        return DB::transaction(function () use ($charge, $reference, $actor, $paymentMethod, $amount, $extensionDays, $note): SubscriptionPayment {
            $this->subscriptions->assertCanManageSubscriptions($actor);

            $lockedCharge = SubscriptionCharge::query()
                ->whereKey($charge->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedCharge->getAttribute('status'), [
                SubscriptionChargeStatus::Open,
                SubscriptionChargeStatus::PartiallyPaid,
            ], true)) {
                throw new DomainException('Only open or partially paid charges can receive payments.');
            }

            $plan = Plan::query()->find((int) $lockedCharge->plan_id);

            if (! $plan instanceof Plan || ! $plan->exists || ! $plan->is_active) {
                throw new DomainException('The requested plan is not available.');
            }

            $outstanding = $lockedCharge->outstandingAmount();
            $resolvedAmount = $amount ?? $outstanding;

            if ($resolvedAmount <= 0) {
                throw new DomainException('Payment amount must be a positive number.');
            }

            if ($resolvedAmount > $outstanding) {
                throw new DomainException('Payment amount cannot exceed the outstanding balance.');
            }

            if (! in_array($paymentMethod, self::MANUAL_METHODS, true)) {
                throw new DomainException('The selected manual payment method is not valid.');
            }

            $normalizedReference = $this->normalizeReference($reference);

            if ($normalizedReference === '') {
                throw new DomainException('A payment reference is required.');
            }

            $rawIntent = $lockedCharge->getAttribute('intent');
            $intent = $rawIntent instanceof SubscriptionPaymentIntent
                ? $rawIntent
                : SubscriptionPaymentIntent::from((string) $rawIntent);

            if ($intent === SubscriptionPaymentIntent::ExtendSubscription && ($extensionDays === null || $extensionDays <= 0)) {
                throw new DomainException('Extension days must be a positive number for an extension payment.');
            }

            if ($this->referenceExists($normalizedReference)) {
                throw new DomainException('A payment with this reference is already recorded.');
            }

            try {
                $payment = SubscriptionPayment::query()->create([
                    'tenant_id' => $lockedCharge->tenant_id,
                    'plan_id' => $lockedCharge->plan_id,
                    'intent' => $intent,
                    'subscription_charge_id' => $lockedCharge->id,
                    'extension_days' => $intent === SubscriptionPaymentIntent::ExtendSubscription ? $extensionDays : null,
                    'status' => SubscriptionPaymentStatus::Pending,
                    'provider' => self::MANUAL_PROVIDER,
                    'payment_method' => $paymentMethod,
                    'currency' => self::SUPPORTED_CURRENCY,
                    'amount' => $resolvedAmount,
                    'reference' => $normalizedReference,
                    'note' => $note,
                    'created_by' => $actor->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new DomainException('A payment with this reference is already recorded.');
            }

            $this->log($payment, $actor, 'subscription-payment.recorded');

            return $payment;
        });
    }

    public function verify(SubscriptionPayment $payment, User $actor): SubscriptionPayment
    {
        return DB::transaction(function () use ($payment, $actor): SubscriptionPayment {
            $this->subscriptions->assertCanManageSubscriptions($actor);

            $locked = SubscriptionPayment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->getAttribute('status') !== SubscriptionPaymentStatus::Pending) {
                throw new DomainException('Only pending payments can be verified.');
            }

            $chargeId = (int) $locked->subscription_charge_id;

            if ($chargeId <= 0) {
                throw new DomainException('The payment is not linked to a subscription charge.');
            }

            $charge = SubscriptionCharge::query()
                ->whereKey($chargeId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($charge->getAttribute('status'), [
                SubscriptionChargeStatus::Open,
                SubscriptionChargeStatus::PartiallyPaid,
            ], true)) {
                throw new DomainException('Only open or partially paid charges can be verified.');
            }

            $plan = Plan::query()->find((int) $charge->plan_id);

            if (! $plan instanceof Plan || ! $plan->exists || ! $plan->is_active) {
                throw new DomainException('The payment plan is no longer available.');
            }

            $amount = (int) $locked->amount;
            $outstanding = $charge->outstandingAmount();

            if ($amount <= 0) {
                throw new DomainException('Payment amount must be a positive number.');
            }

            if ($amount > $outstanding) {
                throw new DomainException('Payment amount cannot exceed the outstanding balance.');
            }

            $tenant = Tenant::query()->findOrFail((int) $locked->tenant_id);
            $intent = $locked->getAttribute('intent');

            if (! $intent instanceof SubscriptionPaymentIntent) {
                throw new DomainException('The payment intent is invalid.');
            }

            $newPaid = (int) $charge->paid_amount + $amount;
            $settled = $newPaid >= (int) $charge->net_amount;

            if ($settled) {
                $this->applyPaymentToSubscription($locked, $tenant, $plan, $actor, $charge);
            }

            $charge->update([
                'paid_amount' => $newPaid,
                'status' => $settled
                    ? SubscriptionChargeStatus::Paid
                    : SubscriptionChargeStatus::PartiallyPaid,
            ]);

            $locked->update([
                'status' => SubscriptionPaymentStatus::Verified,
                'verified_by' => $actor->id,
                'received_at' => now(),
            ]);

            $this->log($locked, $actor, 'subscription-payment.verified');
            $this->logChargeProgress($charge, $actor, $settled);

            return $locked->refresh();
        });
    }

    public function reject(SubscriptionPayment $payment, User $actor, string $reason): SubscriptionPayment
    {
        return DB::transaction(function () use ($payment, $actor, $reason): SubscriptionPayment {
            $this->subscriptions->assertCanManageSubscriptions($actor);

            if (trim($reason) === '') {
                throw new DomainException('A rejection reason is required.');
            }

            $locked = SubscriptionPayment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->getAttribute('status') !== SubscriptionPaymentStatus::Pending) {
                throw new DomainException('Only pending payments can be rejected.');
            }

            $locked->update([
                'status' => SubscriptionPaymentStatus::Rejected,
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
                'rejected_reason' => $reason,
            ]);

            $this->log($locked, $actor, 'subscription-payment.rejected');

            return $locked->refresh();
        });
    }

    /**
     * Resolve a fully paid charge into the matching SubscriptionService
     * operation. Providers never mutate the subscription directly; partial
     * payments only accumulate against the charge and never settle here.
     */
    private function applyPaymentToSubscription(
        SubscriptionPayment $payment,
        Tenant $tenant,
        Plan $plan,
        User $actor,
        SubscriptionCharge $charge,
    ): void {
        $intent = $payment->getAttribute('intent');
        $note = sprintf(
            'Verified subscription payment #%d (charge #%d, provider: %s, reference: %s).',
            (int) $payment->id,
            (int) $charge->id,
            (string) $payment->provider,
            (string) $payment->reference,
        );

        if ($intent === SubscriptionPaymentIntent::ExtendSubscription) {
            $subscription = TenantSubscription::query()
                ->where('tenant_id', $tenant->id)
                ->first();

            if ($subscription === null || (int) $subscription->plan_id !== (int) $plan->id) {
                throw new DomainException('The extension payment plan does not match the current subscription.');
            }

            $status = $subscription->getAttribute('status');

            if ($status instanceof SubscriptionStatus
                && in_array($status, [SubscriptionStatus::Cancelled, SubscriptionStatus::Expired], true)) {
                $this->subscriptions->reactivateSubscription($tenant, $actor, $note);

                return;
            }

            $this->subscriptions->extendSubscription($tenant, (int) $payment->extension_days, $actor, $note);

            return;
        }

        $this->subscriptions->assignPlan($tenant, $plan, $actor, $note);
    }

    private function referenceExists(string $reference): bool
    {
        return SubscriptionPayment::query()
            ->where('provider', self::MANUAL_PROVIDER)
            ->where('reference', $reference)
            ->exists();
    }

    private function normalizeReference(string $reference): string
    {
        return strtoupper(trim($reference));
    }

    private function log(SubscriptionPayment $payment, User $actor, string $event): void
    {
        $activity = activity('subscription-payments')
            ->performedOn($payment)
            ->causedBy($actor)
            ->event($event)
            ->withProperties([
                'payment_id' => $payment->id,
                'charge_id' => $payment->subscription_charge_id,
                'provider' => $payment->provider,
                'reference' => $payment->reference,
            ]);

        $activity->log($event);
    }

    private function logChargeProgress(SubscriptionCharge $charge, User $actor, bool $settled): void
    {
        $event = $settled ? 'subscription-charge.paid' : 'subscription-charge.partially-paid';

        activity('subscription-charges')
            ->performedOn($charge)
            ->causedBy($actor)
            ->event($event)
            ->withProperties([
                'charge_id' => $charge->id,
                'tenant_id' => $charge->tenant_id,
                'plan_id' => $charge->plan_id,
                'paid_amount' => $charge->paid_amount,
                'net_amount' => $charge->net_amount,
                'outstanding_amount' => $charge->outstandingAmount(),
            ])
            ->log($event);
    }
}
