<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Default per-tenant email templates for subscription due / overdue reminders.
 * Consumers: TenantObserver (new tenants) and SubscriptionReminderTemplatesSeeder
 * (backfill for existing tenants). Only safe, user-facing variables are used.
 */
final class ReminderTemplateDefaults
{
    /**
     * @return array<int, array{event_key: string, channel: string, subject: string, body: string, is_platform_managed: bool}>
     */
    public static function definitions(): array
    {
        return [
            [
                'event_key' => 'subscription.charge.reminder.7d',
                'channel' => 'email',
                'subject' => 'Payment due soon — {{ store.name }}',
                'body' => "Hi {{ owner.name }},\n\nYour charge of {{ charge.outstanding }} ({{ charge.currency }}) for the {{ plan.name }} plan is due on {{ charge.due_date }}.\n\nPay before the due date to keep your store active.\n\nPay now: {{ billing.url }}\n\n— {{ store.name }}",
                'is_platform_managed' => true,
            ],
            [
                'event_key' => 'subscription.charge.reminder.3d',
                'channel' => 'email',
                'subject' => 'Payment due in 3 days — {{ store.name }}',
                'body' => "Hi {{ owner.name }},\n\nYour charge of {{ charge.outstanding }} ({{ charge.currency }}) for the {{ plan.name }} plan is due on {{ charge.due_date }}.\n\nPay before the due date to keep your store active.\n\nPay now: {{ billing.url }}\n\n— {{ store.name }}",
                'is_platform_managed' => true,
            ],
            [
                'event_key' => 'subscription.charge.reminder.1d',
                'channel' => 'email',
                'subject' => 'Payment due tomorrow — {{ store.name }}',
                'body' => "Hi {{ owner.name }},\n\nYour charge of {{ charge.outstanding }} ({{ charge.currency }}) for the {{ plan.name }} plan is due tomorrow ({{ charge.due_date }}).\n\nPay before the due date to keep your store active.\n\nPay now: {{ billing.url }}\n\n— {{ store.name }}",
                'is_platform_managed' => true,
            ],
            [
                'event_key' => 'subscription.charge.reminder.due',
                'channel' => 'email',
                'subject' => 'Payment due today — {{ store.name }}',
                'body' => "Hi {{ owner.name }},\n\nYour charge of {{ charge.outstanding }} ({{ charge.currency }}) for the {{ plan.name }} plan is due today ({{ charge.due_date }}).\n\nPay today to keep your store active.\n\nPay now: {{ billing.url }}\n\n— {{ store.name }}",
                'is_platform_managed' => true,
            ],
            [
                'event_key' => 'subscription.charge.reminder.overdue',
                'channel' => 'email',
                'subject' => 'Payment overdue — {{ store.name }}',
                'body' => "Hi {{ owner.name }},\n\nYour charge of {{ charge.outstanding }} ({{ charge.currency }}) for the {{ plan.name }} plan was due on {{ charge.due_date }} and is now overdue.\n\nSettle the outstanding balance to keep your store active.\n\nPay now: {{ billing.url }}\n\n— {{ store.name }}",
                'is_platform_managed' => true,
            ],
        ];
    }
}
