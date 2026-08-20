<?php

declare(strict_types=1);

namespace App\Filament\Platform\Support;

use App\Enums\SubscriptionEventType;
use App\Enums\SubscriptionPaymentIntent;
use App\Enums\SubscriptionPaymentStatus;
use App\Filament\Platform\Resources\SubscriptionPaymentResource;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only presenter for the platform subscription history timeline.
 * Merges immutable SubscriptionEvents, SubscriptionPayment records and
 * plan-change decision Activitylog entries into a single chronological
 * collection. Never mutates state and contains no business logic.
 */
class SubscriptionHistoryPresenter
{
    public static function items(Tenant $tenant, int $limit = 50): array
    {
        $entries = [
            ...self::eventEntries($tenant),
            ...self::paymentEntries($tenant),
            ...self::decisionEntries($tenant),
        ];

        usort($entries, function (array $a, array $b): int {
            if ($a['sort_time']->equalTo($b['sort_time'])) {
                return $b['sort_id'] <=> $a['sort_id'];
            }

            return $a['sort_time']->lt($b['sort_time']) ? 1 : -1;
        });

        return array_slice($entries, 0, max(1, $limit));
    }

    /** @return array<int, array<string, mixed>> */
    private static function eventEntries(Tenant $tenant): array
    {
        return SubscriptionEvent::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->with(['actor', 'fromPlan', 'toPlan'])
            ->latest('effective_at')
            ->get()
            ->map(function (SubscriptionEvent $event): array {
                $timestamp = self::timestampOf($event->getAttribute('effective_at'), $event->getAttribute('created_at'));
                $type = $event->getAttribute('type');
                $fromPlan = $event->getRelation('fromPlan');
                $toPlan = $event->getRelation('toPlan');

                return [
                    'kind' => 'event',
                    'sort_time' => $timestamp,
                    'sort_id' => (int) $event->getKey(),
                    'day' => $timestamp->toDateString(),
                    'time_label' => $timestamp->toDateTimeString(),
                    'label' => $type instanceof SubscriptionEventType ? $type->label() : 'Subscription event',
                    'from_plan' => $fromPlan instanceof Plan ? $fromPlan->name : null,
                    'to_plan' => $toPlan instanceof Plan ? $toPlan->name : null,
                    'actor' => self::eventActor($event),
                    'is_system' => $event->getAttribute('actor_user_id') === null,
                    'note' => $event->getAttribute('note'),
                    'metadata' => (array) ($event->getAttribute('metadata') ?? []),
                ];
            })
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private static function paymentEntries(Tenant $tenant): array
    {
        return SubscriptionPayment::query()
            ->where('tenant_id', $tenant->id)
            ->with(['plan', 'creator', 'verifier', 'rejector'])
            ->latest()
            ->get()
            ->map(function (SubscriptionPayment $payment): array {
                $timestamp = self::timestampOf(
                    $payment->getAttribute('received_at')
                        ?? $payment->getAttribute('rejected_at')
                        ?? $payment->getAttribute('created_at'),
                );
                $status = $payment->getAttribute('status');
                $intent = $payment->getAttribute('intent');
                $statusLabel = $status instanceof SubscriptionPaymentStatus ? $status->label() : (string) $status;

                return [
                    'kind' => 'payment',
                    'sort_time' => $timestamp,
                    'sort_id' => (int) $payment->getKey(),
                    'day' => $timestamp->toDateString(),
                    'time_label' => $timestamp->toDateTimeString(),
                    'label' => 'Payment '.$statusLabel,
                    'status' => $statusLabel,
                    'status_value' => $status instanceof SubscriptionPaymentStatus ? $status->value : (string) $status,
                    'intent' => $intent instanceof SubscriptionPaymentIntent ? $intent->label() : (string) $intent,
                    'amount' => (int) $payment->amount,
                    'provider' => (string) $payment->provider,
                    'payment_method' => (string) $payment->payment_method,
                    'reference' => (string) $payment->reference,
                    'actor' => self::userNameOrNull($payment->getRelation('creator')) ?? 'System',
                    'verifier' => self::userNameOrNull($payment->getRelation('verifier')),
                    'rejector' => self::userNameOrNull($payment->getRelation('rejector')),
                    'rejected_reason' => $payment->getAttribute('rejected_reason'),
                    'url' => SubscriptionPaymentResource::getUrl('view', ['record' => $payment]),
                ];
            })
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private static function decisionEntries(Tenant $tenant): array
    {
        $activities = Activity::query()
            ->where('log_name', 'subscriptions')
            ->where('subject_type', PlanChangeRequest::class)
            ->where('properties->tenant_id', $tenant->id)
            ->with('causer')
            ->latest()
            ->get();

        $planNames = self::planNamesForActivities($activities);

        return $activities
            ->map(function (Activity $activity) use ($planNames): array {
                $timestamp = self::timestampOf($activity->getAttribute('created_at'));
                $properties = (array) ($activity->getAttribute('properties') ?? []);
                $action = str_contains((string) $activity->getAttribute('event'), 'approved') ? 'approved' : 'rejected';
                $requestedPlanId = isset($properties['requested_plan_id']) ? (int) $properties['requested_plan_id'] : null;

                return [
                    'kind' => 'decision',
                    'sort_time' => $timestamp,
                    'sort_id' => (int) $activity->getKey(),
                    'day' => $timestamp->toDateString(),
                    'time_label' => $timestamp->toDateTimeString(),
                    'label' => 'Plan change '.$action,
                    'action' => $action,
                    'requested_plan' => $requestedPlanId !== null && isset($planNames[$requestedPlanId])
                        ? $planNames[$requestedPlanId]
                        : null,
                    'actor' => self::decisionActor($activity),
                    'is_system' => $activity->getAttribute('causer_id') === null,
                    'request_id' => isset($properties['request_id']) ? (int) $properties['request_id'] : null,
                    'reason' => null,
                ];
            })
            ->all();
    }

    private static function timestampOf(mixed $timestamp, mixed $fallback = null): CarbonInterface
    {
        if (! $timestamp instanceof CarbonInterface) {
            $timestamp = $fallback;
        }

        if (! $timestamp instanceof CarbonInterface) {
            $timestamp = now();
        }

        return $timestamp;
    }

    private static function eventActor(SubscriptionEvent $event): string
    {
        if ($event->getAttribute('actor_user_id') === null) {
            return 'System';
        }

        $actor = $event->getRelation('actor');

        return $actor instanceof User ? $actor->name : 'Unknown actor';
    }

    private static function decisionActor(Activity $activity): string
    {
        if ($activity->getAttribute('causer_id') === null) {
            return 'System';
        }

        $causer = $activity->getRelation('causer');

        return $causer instanceof User ? $causer->name : 'Unknown actor';
    }

    private static function userNameOrNull(mixed $user): ?string
    {
        return $user instanceof User ? $user->name : null;
    }

    /** @param Collection<int, Activity> $activities @return array<int, string> */
    private static function planNamesForActivities(Collection $activities): array
    {
        $planIds = $activities
            ->map(fn (Activity $activity): ?int => isset(((array) ($activity->getAttribute('properties') ?? []))['requested_plan_id'])
                ? (int) ((array) ($activity->getAttribute('properties') ?? []))['requested_plan_id']
                : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($planIds === []) {
            return [];
        }

        return Plan::query()
            ->whereIn('id', $planIds)
            ->pluck('name', 'id')
            ->map(fn (mixed $name): string => (string) $name)
            ->all();
    }
}
