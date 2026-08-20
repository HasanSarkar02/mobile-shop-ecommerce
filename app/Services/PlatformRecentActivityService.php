<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionEventType;
use App\Filament\Platform\Resources\DomainResource;
use App\Filament\Platform\Resources\TenantResource;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\SubscriptionEvent;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only presenter of the most recent platform-wide activity. Merges
 * immutable SubscriptionEvents with plan-change decision and domain lifecycle
 * Activitylog entries into a single chronological, bounded list. Every source
 * is one bounded, eager-loaded query (no N+1) and only safe operational fields
 * are exposed — never tokens, digests, payloads or sensitive metadata.
 */
class PlatformRecentActivityService
{
    private const SOURCE_LIMIT = 20;

    private const RESULT_LIMIT = 10;

    /**
     * @return list<array{
     *     sort_time: CarbonInterface,
     *     sort_id: int,
     *     time_label: string,
     *     badge: string,
     *     tenant: string,
     *     label: string,
     *     actor: string,
     *     note: ?string,
     *     url: ?string
     * }>
     */
    public function items(int $limit = self::RESULT_LIMIT): array
    {
        $entries = [
            ...$this->subscriptionEventEntries(),
            ...$this->planChangeDecisionEntries(),
            ...$this->domainActivityEntries(),
        ];

        usort($entries, function (array $a, array $b): int {
            if ($a['sort_time']->equalTo($b['sort_time'])) {
                return $b['sort_id'] <=> $a['sort_id'];
            }

            return $a['sort_time']->lt($b['sort_time']) ? 1 : -1;
        });

        return array_slice($entries, 0, max(1, $limit));
    }

    /** @return list<array<string, mixed>> */
    private function subscriptionEventEntries(): array
    {
        $events = SubscriptionEvent::query()
            ->withoutGlobalScope('tenant')
            ->with(['actor', 'fromPlan', 'toPlan'])
            ->latest('effective_at')
            ->limit(self::SOURCE_LIMIT)
            ->get();

        $tenantNames = $this->tenantNames(
            $events->map(fn (SubscriptionEvent $event): int => (int) $event->getAttribute('tenant_id'))->all(),
        );

        return $events
            ->map(function (SubscriptionEvent $event) use ($tenantNames): array {
                $timestamp = $this->timestampOf($event->getAttribute('effective_at'), $event->getAttribute('created_at'));
                $type = $event->getAttribute('type');

                return [
                    'sort_time' => $timestamp,
                    'sort_id' => (int) $event->getKey(),
                    'time_label' => $timestamp->toDateTimeString(),
                    'badge' => $type instanceof SubscriptionEventType ? $type->label() : 'Subscription event',
                    'tenant' => $this->tenantName((int) $event->getAttribute('tenant_id'), $tenantNames),
                    'label' => $this->eventLabel($type, $event->getRelation('toPlan')),
                    'actor' => $this->eventActor($event),
                    'note' => $this->nullableString($event->getAttribute('note')),
                    'url' => TenantResource::getUrl('view', ['record' => $event->getAttribute('tenant_id')]),
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function planChangeDecisionEntries(): array
    {
        $activities = Activity::query()
            ->where('log_name', 'subscriptions')
            ->where('subject_type', PlanChangeRequest::class)
            ->with('causer')
            ->latest()
            ->limit(self::SOURCE_LIMIT)
            ->get();

        $tenantNames = $this->tenantNames($this->propertyIds($activities, 'tenant_id'));
        $planNames = $this->planNamesForActivities($activities);
        $reasons = $this->rejectionReasonsForActivities($activities);

        return $activities
            ->map(function (Activity $activity) use ($tenantNames, $planNames, $reasons): array {
                $timestamp = $this->timestampOf($activity->getAttribute('created_at'));
                $properties = $this->activityProperties($activity);
                $approved = str_contains((string) $activity->getAttribute('event'), 'approved');
                $tenantId = isset($properties['tenant_id']) ? (int) $properties['tenant_id'] : 0;
                $requestedPlanId = isset($properties['requested_plan_id']) ? (int) $properties['requested_plan_id'] : null;
                $requestId = isset($properties['request_id']) ? (int) $properties['request_id'] : null;

                return [
                    'sort_time' => $timestamp,
                    'sort_id' => (int) $activity->getKey(),
                    'time_label' => $timestamp->toDateTimeString(),
                    'badge' => $approved ? 'Plan request approved' : 'Plan request rejected',
                    'tenant' => $this->tenantName($tenantId, $tenantNames),
                    'label' => $this->decisionLabel($approved, $requestedPlanId, $planNames),
                    'actor' => $this->actorName($activity->getAttribute('causer_id'), $activity->getRelation('causer')),
                    'note' => $this->rejectionReason($approved, $requestId, $reasons),
                    'url' => $tenantId > 0 ? TenantResource::getUrl('view', ['record' => $tenantId]) : null,
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function domainActivityEntries(): array
    {
        $activities = Activity::query()
            ->where('log_name', 'domains')
            ->with(['subject.tenant', 'causer'])
            ->latest()
            ->limit(self::SOURCE_LIMIT)
            ->get();

        return $activities
            ->map(function (Activity $activity): array {
                $timestamp = $this->timestampOf($activity->getAttribute('created_at'));
                $subject = $activity->getRelation('subject');
                $properties = $this->activityProperties($activity);
                $event = (string) $activity->getAttribute('event');
                $hostname = $subject instanceof Domain ? (string) $subject->getAttribute('domain') : null;
                $tenant = $subject instanceof Domain ? $subject->getRelation('tenant') : null;
                $tenantId = $subject instanceof Domain ? (int) $subject->getAttribute('tenant_id') : 0;

                return [
                    'sort_time' => $timestamp,
                    'sort_id' => (int) $activity->getKey(),
                    'time_label' => $timestamp->toDateTimeString(),
                    'badge' => $this->domainBadge($event),
                    'tenant' => $tenant instanceof Tenant && (string) $tenant->getAttribute('name') !== ''
                        ? (string) $tenant->getAttribute('name')
                        : (string) $tenantId,
                    'label' => $hostname !== null ? $hostname.' '.$this->domainVerb($event) : $this->domainBadge($event),
                    'actor' => $this->actorName($activity->getAttribute('causer_id'), $activity->getRelation('causer')),
                    'note' => $this->safeNote($properties),
                    'url' => $subject instanceof Domain ? DomainResource::getUrl('view', ['record' => $subject]) : null,
                ];
            })
            ->values()
            ->all();
    }

    private function eventLabel(mixed $type, mixed $toPlan): string
    {
        if (! $type instanceof SubscriptionEventType) {
            return 'Subscription event';
        }

        $label = $type->label();

        if ($toPlan instanceof Plan && (string) $toPlan->getAttribute('name') !== ''
            && in_array($type, [SubscriptionEventType::Subscribed, SubscriptionEventType::Upgraded, SubscriptionEventType::Downgraded], true)) {
            return $label.' to '.$toPlan->getAttribute('name');
        }

        return $label;
    }

    /** @param array<int, string> $planNames */
    private function decisionLabel(bool $approved, ?int $requestedPlanId, array $planNames): string
    {
        $label = $approved ? 'Plan change approved' : 'Plan change rejected';

        if ($requestedPlanId !== null && isset($planNames[$requestedPlanId])) {
            $label .= ' to '.$planNames[$requestedPlanId];
        }

        return $label;
    }

    /** @param array<int, string> $reasons */
    private function rejectionReason(bool $approved, ?int $requestId, array $reasons): ?string
    {
        if ($approved || $requestId === null) {
            return null;
        }

        $reason = $reasons[$requestId] ?? null;

        return is_string($reason) && $reason !== '' ? $reason : null;
    }

    private function domainBadge(string $event): string
    {
        return match ($event) {
            'domain.created' => 'Domain created',
            'domain.verification_initiated' => 'Verification initiated',
            'domain.verification_regenerated' => 'Verification regenerated',
            'domain.verification_check_started' => 'Verification check started',
            'domain.verification_check_stale' => 'Verification check stale',
            'domain.verified' => 'Domain verified',
            'domain.verification_failed' => 'Domain verification failed',
            'domain.activated' => 'Domain activated',
            'domain.suspended' => 'Domain suspended',
            'domain.revoked' => 'Domain revoked',
            'domain.primary_changed' => 'Primary domain changed',
            'domain.primary_cleared' => 'Primary domain cleared',
            'domain.pending_removed' => 'Pending domain removed',
            default => 'Domain activity',
        };
    }

    private function domainVerb(string $event): string
    {
        return str_replace('_', ' ', Str::after($event, 'domain.'));
    }

    /** @param array<string, mixed> $properties */
    private function safeNote(array $properties): ?string
    {
        foreach (['reason', 'failure_message'] as $key) {
            $value = $properties[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function activityProperties(Activity $activity): array
    {
        $properties = $activity->getAttribute('properties');

        return $properties instanceof Collection
            ? $properties->toArray()
            : (array) ($properties ?? []);
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return list<int>
     */
    private function propertyIds(Collection $activities, string $key): array
    {
        return $activities
            ->map(function (Activity $activity) use ($key): ?int {
                $properties = $this->activityProperties($activity);

                return isset($properties[$key]) ? (int) $properties[$key] : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @param list<int> $tenantIds @return array<int, string> */
    private function tenantNames(array $tenantIds): array
    {
        $tenantIds = array_values(array_unique(array_filter($tenantIds, fn (int $id): bool => $id > 0)));

        if ($tenantIds === []) {
            return [];
        }

        return Tenant::query()
            ->whereIn('id', $tenantIds)
            ->pluck('name', 'id')
            ->map(fn (mixed $name): string => (string) $name)
            ->all();
    }

    /** @param array<int, string> $names */
    private function tenantName(int $tenantId, array $names): string
    {
        return isset($names[$tenantId]) && $names[$tenantId] !== '' ? $names[$tenantId] : (string) $tenantId;
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return array<int, string>
     */
    private function planNamesForActivities(Collection $activities): array
    {
        $planIds = $this->propertyIds($activities, 'requested_plan_id');

        if ($planIds === []) {
            return [];
        }

        return Plan::query()
            ->whereIn('id', $planIds)
            ->pluck('name', 'id')
            ->map(fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * @param  Collection<int, Activity>  $activities
     * @return array<int, string>
     */
    private function rejectionReasonsForActivities(Collection $activities): array
    {
        $requestIds = $this->propertyIds($activities, 'request_id');

        if ($requestIds === []) {
            return [];
        }

        return PlanChangeRequest::query()
            ->withoutGlobalScope('tenant')
            ->whereIn('id', $requestIds)
            ->pluck('rejection_reason', 'id')
            ->map(fn (mixed $value): string => $value === null ? '' : (string) $value)
            ->all();
    }

    private function eventActor(SubscriptionEvent $event): string
    {
        return $this->actorName(
            $event->getAttribute('actor_user_id') !== null ? (int) $event->getAttribute('actor_user_id') : null,
            $event->getRelation('actor'),
        );
    }

    private function actorName(?int $causerId, mixed $causer): string
    {
        if ($causerId === null) {
            return 'System';
        }

        return $causer instanceof User ? (string) $causer->getAttribute('name') : 'Unknown actor';
    }

    private function timestampOf(mixed $timestamp, mixed $fallback = null): CarbonInterface
    {
        if (! $timestamp instanceof CarbonInterface) {
            $timestamp = $fallback;
        }

        if (! $timestamp instanceof CarbonInterface) {
            $timestamp = now();
        }

        return $timestamp;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
