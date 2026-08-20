<?php

declare(strict_types=1);

namespace App\Filament\Platform\Pages;

use App\Filament\Platform\Resources\DomainResource;
use App\Filament\Platform\Resources\PlanChangeRequestResource;
use App\Filament\Platform\Resources\PlatformAdminResource;
use App\Filament\Platform\Resources\SubscriptionChargeResource;
use App\Filament\Platform\Resources\SubscriptionPaymentResource;
use App\Filament\Platform\Resources\TenantResource;
use App\Filament\Platform\Resources\TenantSubscriptionResource;
use App\Filament\Platform\Support\PlatformMoney;
use App\Filament\Platform\Widgets\PlatformStatsOverview;
use App\Services\PlatformDashboardService;
use App\Services\PlatformRecentActivityService;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;

class PlatformDashboard extends Dashboard
{
    protected string $view = 'filament.platform.pages.dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    public static function canAccess(array $parameters = []): bool
    {
        return Filament::auth()->user()?->canAccessPanel(Filament::getPanel('platform')) ?? false;
    }

    public function getHeaderWidgets(): array
    {
        return [
            PlatformStatsOverview::class,
        ];
    }

    public function getWidgets(): array
    {
        return [];
    }

    /** @return list<array{label: string, icon: string, url: string}> */
    public function getQuickLinks(): array
    {
        return [
            ['label' => 'Tenants', 'icon' => 'heroicon-o-building-storefront', 'url' => TenantResource::getUrl('index')],
            ['label' => 'Subscriptions', 'icon' => 'heroicon-o-credit-card', 'url' => TenantSubscriptionResource::getUrl('index')],
            ['label' => 'Subscription Charges', 'icon' => 'heroicon-o-banknotes', 'url' => SubscriptionChargeResource::getUrl('index')],
            ['label' => 'Subscription Payments', 'icon' => 'heroicon-o-arrow-path', 'url' => SubscriptionPaymentResource::getUrl('index')],
            ['label' => 'Plan Change Requests', 'icon' => 'heroicon-o-arrows-right-left', 'url' => PlanChangeRequestResource::getUrl('index')],
            ['label' => 'Domains', 'icon' => 'heroicon-o-globe-alt', 'url' => DomainResource::getUrl('index')],
            ['label' => 'Platform Admins', 'icon' => 'heroicon-o-user-group', 'url' => PlatformAdminResource::getUrl('index')],
        ];
    }

    /**
     * Fresh operational alerts rendered as cards in the dashboard's Action
     * Required section. Each alert links straight into its resource with the
     * matching filter pre-applied so the platform admin can act on it
     * immediately.
     *
     * @return list<array{key: string, label: string, description: string, count: int, detail: ?string, url: string, tone: string}>
     */
    public function getOperationalAlerts(): array
    {
        $definitions = [
            'expiring_subscriptions' => [
                'label' => 'Subscriptions expiring within 7 days',
                'description' => 'Active or trialing subscriptions renewing or ending within the next 7 days.',
                'tone' => 'warning',
                'url' => TenantSubscriptionResource::getUrl('index', ['tableFilters[expiring][value]' => 'within_7_days']),
            ],
            'pending_plan_change_requests' => [
                'label' => 'Pending plan change requests',
                'description' => 'Plan change requests awaiting your decision.',
                'tone' => 'warning',
                'url' => PlanChangeRequestResource::getUrl('index', ['tableFilters[status][value]' => 'pending']),
            ],
            'pending_subscription_payments' => [
                'label' => 'Pending subscription payments',
                'description' => 'Payments recorded but not yet verified.',
                'tone' => 'warning',
                'url' => SubscriptionPaymentResource::getUrl('index', ['tableFilters[status][value]' => 'pending']),
            ],
            'outstanding_subscription_charges' => [
                'label' => 'Outstanding subscription charges',
                'description' => 'Charges with an unpaid balance requiring collection.',
                'tone' => 'danger',
                'url' => SubscriptionChargeResource::getUrl('index', ['tableFilters[outstanding][value]' => 'open_or_partially_paid']),
            ],
            'rejected_subscription_payments' => [
                'label' => 'Recently rejected subscription payments',
                'description' => 'Payments rejected in the last 7 days.',
                'tone' => 'danger',
                'url' => SubscriptionPaymentResource::getUrl('index', ['tableFilters[status][value]' => 'rejected']),
            ],
        ];

        $alerts = [];

        foreach (app(PlatformDashboardService::class)->operationalAlerts() as $item) {
            $definition = $definitions[$item['key']] ?? null;

            if ($definition === null) {
                continue;
            }

            $alerts[] = [
                'key' => $item['key'],
                'label' => $definition['label'],
                'description' => $definition['description'],
                'count' => $item['count'],
                'detail' => $item['key'] === 'outstanding_subscription_charges' && isset($item['amount'])
                    ? PlatformMoney::format($item['amount'])
                    : null,
                'url' => $definition['url'],
                'tone' => $definition['tone'],
            ];
        }

        return $alerts;
    }

    public function hasOperationalAlerts(): bool
    {
        return app(PlatformDashboardService::class)->operationalAlerts() !== [];
    }

    /**
     * Fresh DNS / domain health alerts rendered as cards in the dashboard's
     * Action Required section. Each alert links straight into the Domains
     * resource with the matching status filter pre-applied. The failed alert
     * also carries a bounded list of recently failed domains (safe operational
     * fields only) so the platform admin can see why verification failed at a
     * glance.
     *
     * @return list<array{key: string, label: string, view_label: string, description: string, count: int, url: string, tone: string, domains: list<array{domain: string, tenant: string, failure_code: ?string, failure_message: ?string, last_checked_at: ?string, attempts: int}>}>
     */
    public function getDnsHealthAlerts(): array
    {
        $definitions = [
            'pending_domains' => [
                'label' => 'Pending verification',
                'view_label' => 'View Pending',
                'description' => 'Custom domains waiting for DNS TXT verification.',
                'tone' => 'warning',
                'url' => DomainResource::getUrl('index', ['tableFilters[status][value]' => 'pending']),
            ],
            'failed_domains' => [
                'label' => 'Failed verification',
                'view_label' => 'View Failed',
                'description' => 'Custom domains whose last verification check failed.',
                'tone' => 'danger',
                'url' => DomainResource::getUrl('index', ['tableFilters[status][value]' => 'failed']),
            ],
        ];

        $alerts = [];

        foreach (app(PlatformDashboardService::class)->dnsHealthAlerts() as $item) {
            $definition = $definitions[$item['key']] ?? null;

            if ($definition === null) {
                continue;
            }

            $alerts[] = [
                'key' => $item['key'],
                'label' => $definition['label'],
                'view_label' => $definition['view_label'],
                'description' => $definition['description'],
                'count' => $item['count'],
                'url' => $definition['url'],
                'tone' => $definition['tone'],
                'domains' => $item['domains'] ?? [],
            ];
        }

        return $alerts;
    }

    public function hasDnsHealthAlerts(): bool
    {
        return app(PlatformDashboardService::class)->dnsHealthAlerts() !== [];
    }

    /**
     * Fresh system health (queue backlog, scheduler heartbeat, DB/cache
     * probes, app environment/version) for the System Health section. Values
     * are already sanitised for display by the service.
     *
     * @return array{
     *     queue: array{failed_jobs_count: int, recent_failed_jobs: list<array{queue: string, failed_at: string, exception: string}>, pending_jobs_count: int, oldest_pending_age_seconds: ?int},
     *     scheduler: array{heartbeat_at: ?string, age_seconds: ?int, status: string},
     *     app: array{environment: string, version: ?string, database: string, cache: string}
     * }
     */
    public function getSystemHealth(): array
    {
        return app(PlatformDashboardService::class)->systemHealth();
    }

    /**
     * Fresh, bounded list of the most recent platform-wide activity (immutable
     * subscription events plus plan-change decision and domain lifecycle
     * Activitylog entries). Entries are already normalised and sanitised by
     * the presenter for display.
     *
     * @return list<array{sort_time: CarbonInterface, sort_id: int, time_label: string, badge: string, tenant: string, label: string, actor: string, note: ?string, url: ?string}>
     */
    public function getRecentActivity(): array
    {
        return app(PlatformRecentActivityService::class)->items();
    }
}
