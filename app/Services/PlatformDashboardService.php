<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DomainStatus;
use App\Enums\PlanChangeRequestStatus;
use App\Enums\SubscriptionChargeStatus;
use App\Enums\SubscriptionPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Domain;
use App\Models\PlanChangeRequest;
use App\Models\SubscriptionCharge;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Aggregate business metrics for the platform dashboard. Every value is a
 * single aggregate query — no per-row loading — and the whole set is cached
 * briefly (10 minutes) so the dashboard never turns into a query fan-out.
 * PlanChangeRequest is tenant-scoped, so the pending count bypasses the scope
 * explicitly; every other source is already a central (non-tenant) table.
 */
class PlatformDashboardService
{
    private const CACHE_KEY = 'platform.dashboard.kpis';

    private const CACHE_TTL_SECONDS = 600;

    private const DNS_ALERT_RECENT_LIMIT = 10;

    private const SYSTEM_HEALTH_FAILED_JOB_LIMIT = 10;

    private const SYSTEM_HEALTH_EXCEPTION_LIMIT = 200;

    /**
     * @return array<string, int> keyed by metric name; money values are integer
     *                            minor units and must be formatted before display
     */
    public function kpis(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn (): array => $this->compute());
    }

    /**
     * Fresh, uncached operational alerts. Each entry is a single bounded
     * aggregate query, so this never turns into a query fan-out, and alerts
     * reflect current state rather than the cached KPI snapshot.
     *
     * @return list<array{key: string, count: int, amount?: int}> only alerts
     *                                                            whose count is greater than zero; amount (minor units) is
     *                                                            present for the outstanding charges alert only
     */
    public function operationalAlerts(): array
    {
        $outstanding = $this->outstandingChargesSummary();

        $alerts = [
            ['key' => 'expiring_subscriptions', 'count' => $this->expiringSubscriptionCount()],
            ['key' => 'pending_plan_change_requests', 'count' => $this->pendingPlanChangeRequestCount()],
            ['key' => 'pending_subscription_payments', 'count' => $this->pendingSubscriptionPaymentCount()],
            ['key' => 'outstanding_subscription_charges', 'count' => $outstanding['count'], 'amount' => $outstanding['amount']],
            ['key' => 'rejected_subscription_payments', 'count' => $this->recentlyRejectedPaymentCount()],
        ];

        return array_values(array_filter($alerts, fn (array $alert): bool => $alert['count'] > 0));
    }

    /**
     * Fresh, uncached DNS health alerts for the dashboard's DNS / Domain
     * Operations band. Each alert is a bounded aggregate query; the failed
     * alert also carries a bounded listing of the most recently checked failed
     * domains with only safe operational fields (tenant eager-loaded, no
     * verification secrets).
     *
     * @return list<array{key: string, count: int, domains?: list<array{domain: string, tenant: string, failure_code: ?string, failure_message: ?string, last_checked_at: ?string, attempts: int}>}>
     *                                                                                                                                                                                               only alerts whose count is greater than zero
     */
    public function dnsHealthAlerts(): array
    {
        $failed = $this->failedDomainSummary();

        $alerts = [
            ['key' => 'pending_domains', 'count' => $this->pendingDomainCount()],
            ['key' => 'failed_domains', 'count' => $failed['count'], 'domains' => $failed['domains']],
        ];

        return array_values(array_filter($alerts, fn (array $alert): bool => $alert['count'] > 0));
    }

    /**
     * Fresh system health for the dashboard's System Health section. Queue
     * figures are application-level backlog signals only — nothing here claims
     * worker/process health. Health probes expose just OK/FAILED and never any
     * connection details or exception internals.
     *
     * @return array{
     *     queue: array{failed_jobs_count: int, recent_failed_jobs: list<array{queue: string, failed_at: string, exception: string}>, pending_jobs_count: int, oldest_pending_age_seconds: ?int},
     *     scheduler: array{heartbeat_at: ?string, age_seconds: ?int, status: string},
     *     app: array{environment: string, version: ?string, database: string, cache: string}
     * }
     */
    public function systemHealth(): array
    {
        return [
            'queue' => $this->queueHealth(),
            'scheduler' => $this->schedulerHealth(),
            'app' => [
                'environment' => (string) config('app.env'),
                'version' => $this->appVersion(),
                'database' => $this->databaseProbe(),
                'cache' => $this->cacheProbe(),
            ],
        ];
    }

    /** Lightweight database probe. Returns only OK or FAILED. */
    public function databaseProbe(): string
    {
        try {
            DB::select('select 1');

            return 'OK';
        } catch (Throwable $e) {
            report($e);

            return 'FAILED';
        }
    }

    /** Lightweight cache write/read probe. Returns only OK or FAILED. */
    public function cacheProbe(): string
    {
        $key = 'platform.system-health.probe.'.Str::random(16);

        try {
            Cache::put($key, 'ok', now()->addMinute());
            $value = Cache::get($key);

            return $value === 'ok' ? 'OK' : 'FAILED';
        } catch (Throwable $e) {
            report($e);

            return 'FAILED';
        } finally {
            try {
                Cache::forget($key);
            } catch (Throwable) {
                // best-effort probe cleanup
            }
        }
    }

    /**
     * @return array{failed_jobs_count: int, recent_failed_jobs: list<array{queue: string, failed_at: string, exception: string}>, pending_jobs_count: int, oldest_pending_age_seconds: ?int}
     */
    private function queueHealth(): array
    {
        return [
            'failed_jobs_count' => $this->failedJobCount(),
            'recent_failed_jobs' => $this->recentFailedJobs(),
            'pending_jobs_count' => $this->pendingJobCount(),
            'oldest_pending_age_seconds' => $this->oldestPendingAge(),
        ];
    }

    /**
     * Safe, bounded listing of the most recently failed jobs. Only queue,
     * failed_at and a short exception headline are returned — the payload and
     * full stack trace are never selected or exposed.
     *
     * @return list<array{queue: string, failed_at: string, exception: string}>
     */
    private function recentFailedJobs(): array
    {
        return DB::table('failed_jobs')
            ->select(['queue', 'failed_at', 'exception'])
            ->latest('failed_at')
            ->limit(self::SYSTEM_HEALTH_FAILED_JOB_LIMIT)
            ->get()
            ->map(function (object $job): array {
                $row = (array) $job;

                return [
                    'queue' => (string) ($row['queue'] ?? ''),
                    'failed_at' => Carbon::parse((string) ($row['failed_at'] ?? ''))->toDateTimeString(),
                    'exception' => $this->truncateException((string) ($row['exception'] ?? '')),
                ];
            })
            ->values()
            ->all();
    }

    private function truncateException(string $exception): string
    {
        $headline = Str::of($exception)->before("\n")->trim()->toString();

        return Str::limit($headline, self::SYSTEM_HEALTH_EXCEPTION_LIMIT, '…');
    }

    private function failedJobCount(): int
    {
        return DB::table('failed_jobs')->count();
    }

    private function pendingJobCount(): int
    {
        return DB::table('jobs')->count();
    }

    private function oldestPendingAge(): ?int
    {
        $oldest = DB::table('jobs')->min('available_at');

        if ($oldest === null) {
            return null;
        }

        return max(0, now()->getTimestamp() - (int) $oldest);
    }

    /**
     * @return array{heartbeat_at: ?string, age_seconds: ?int, status: string}
     */
    private function schedulerHealth(): array
    {
        return app(SchedulerHeartbeatService::class)->status(SchedulerHeartbeatService::NAME_APPLICATION);
    }

    private function appVersion(): ?string
    {
        $version = config('app.version');

        return is_string($version) && $version !== '' ? $version : null;
    }

    /** @return array<string, int> */
    private function compute(): array
    {
        $outstanding = $this->outstandingChargesSummary();

        return [
            'total_tenants' => Tenant::query()->count(),
            'active_tenants' => Tenant::query()->where('status', 'active')->count(),
            'trial_tenants' => Tenant::query()->where('status', 'trial')->count(),
            'expiring_subscriptions' => $this->expiringSubscriptionCount(),
            'pending_plan_change_requests' => $this->pendingPlanChangeRequestCount(),
            'pending_subscription_payments' => $this->pendingSubscriptionPaymentCount(),
            'outstanding_charges' => $outstanding['count'],
            'outstanding_amount' => $outstanding['amount'],
            'active_domains' => $this->activeDomainCount(),
            'dns_pending' => $this->pendingDomainCount(),
            'dns_failed' => $this->failedDomainCount(),
        ];
    }

    private function expiringSubscriptionCount(): int
    {
        return TenantSubscription::query()
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Trialing->value,
            ])
            ->whereBetween('current_period_ends_at', [now(), now()->addDays(7)])
            ->count();
    }

    private function pendingPlanChangeRequestCount(): int
    {
        return PlanChangeRequest::query()
            ->withoutGlobalScope('tenant')
            ->where('status', PlanChangeRequestStatus::Pending->value)
            ->count();
    }

    private function pendingSubscriptionPaymentCount(): int
    {
        return SubscriptionPayment::query()
            ->where('status', SubscriptionPaymentStatus::Pending->value)
            ->count();
    }

    /**
     * @return array{count: int, amount: int} count of outstanding charges and
     *                                        the sum of their unpaid remainder
     *                                        in integer minor units
     */
    private function outstandingChargesSummary(): array
    {
        $statuses = [
            SubscriptionChargeStatus::Open->value,
            SubscriptionChargeStatus::PartiallyPaid->value,
        ];

        $base = SubscriptionCharge::query()
            ->whereIn('status', $statuses)
            ->whereColumn('net_amount', '>', 'paid_amount');

        return [
            'count' => (clone $base)->count(),
            'amount' => (int) (clone $base)
                ->selectRaw('COALESCE(SUM(net_amount - paid_amount), 0) as total')
                ->value('total'),
        ];
    }

    private function recentlyRejectedPaymentCount(): int
    {
        return SubscriptionPayment::query()
            ->where('status', SubscriptionPaymentStatus::Rejected->value)
            ->where('rejected_at', '>=', now()->subDays(7))
            ->count();
    }

    private function activeDomainCount(): int
    {
        return Domain::query()->where('status', DomainStatus::Active->value)->count();
    }

    private function pendingDomainCount(): int
    {
        return Domain::query()->where('status', DomainStatus::Pending->value)->count();
    }

    private function failedDomainCount(): int
    {
        return Domain::query()->where('status', DomainStatus::Failed->value)->count();
    }

    /**
     * @return array{count: int, domains: list<array{domain: string, tenant: string, failure_code: ?string, failure_message: ?string, last_checked_at: ?string, attempts: int}>}
     */
    private function failedDomainSummary(): array
    {
        $base = Domain::query()->where('status', DomainStatus::Failed->value);

        $recent = (clone $base)
            ->with('tenant:id,name')
            ->orderByDesc('last_checked_at')
            ->limit(self::DNS_ALERT_RECENT_LIMIT)
            ->get([
                'id',
                'tenant_id',
                'domain',
                'verification_failure_code',
                'verification_failure_message',
                'last_checked_at',
                'verification_attempts',
            ]);

        $domains = $recent
            ->map(fn (Domain $domain): array => [
                'domain' => (string) $domain->getAttribute('domain'),
                'tenant' => $domain->tenant instanceof Tenant
                    ? (string) $domain->tenant->getAttribute('name')
                    : (string) $domain->getAttribute('tenant_id'),
                'failure_code' => $domain->getAttribute('verification_failure_code') !== null
                    ? (string) $domain->getAttribute('verification_failure_code')
                    : null,
                'failure_message' => $domain->getAttribute('verification_failure_message') !== null
                    ? (string) $domain->getAttribute('verification_failure_message')
                    : null,
                'last_checked_at' => $domain->getAttribute('last_checked_at')?->toDateTimeString(),
                'attempts' => (int) $domain->getAttribute('verification_attempts'),
            ])
            ->values()
            ->all();

        return [
            'count' => (clone $base)->count(),
            'domains' => $domains,
        ];
    }
}
