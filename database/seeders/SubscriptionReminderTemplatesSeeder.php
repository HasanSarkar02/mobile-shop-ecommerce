<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\NotificationTemplate;
use App\Models\Tenant;
use App\Support\ReminderTemplateDefaults;
use Illuminate\Database\Seeder;

/**
 * Backfills the default subscription due / overdue reminder notification
 * templates for tenants created before the reminder feature shipped.
 * Idempotent: the (tenant_id, event_key, channel) unique index makes a
 * second run a no-op. New tenants receive the same defaults through
 * TenantObserver.
 */
class SubscriptionReminderTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = ReminderTemplateDefaults::definitions();

        Tenant::query()
            ->select('id')
            ->pluck('id')
            ->each(function (int $tenantId) use ($definitions): void {
                foreach ($definitions as $definition) {
                    NotificationTemplate::query()
                        ->withoutGlobalScope('tenant')
                        ->updateOrCreate(
                            [
                                'tenant_id' => $tenantId,
                                'event_key' => $definition['event_key'],
                                'channel' => $definition['channel'],
                            ],
                            [
                                'subject' => $definition['subject'],
                                'body' => $definition['body'],
                                'is_active' => true,
                                'is_platform_managed' => $definition['is_platform_managed'],
                            ],
                        );
                }
            });
    }
}
