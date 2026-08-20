<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Location;
use App\Models\NotificationTemplate;
use App\Models\StoreSetting;
use App\Models\StoreThemeSetting;
use App\Models\Tenant;
use App\Support\ReminderTemplateDefaults;

class TenantObserver
{
    public function created(Tenant $tenant): void
    {
        Location::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Store',
            'type' => 'store',
            'is_default' => true,
            'is_active' => true,
        ]);

        StoreThemeSetting::query()->create([
            'tenant_id' => $tenant->id,
            'primary_color' => '#16a34a',
        ]);

        StoreSetting::query()->create([
            'tenant_id' => $tenant->id,
        ]);

        $defaults = [
            ['event_key' => 'order.placed', 'channel' => 'email', 'subject' => 'Order Confirmed - {{ order.number }}', 'body' => "Hi {{ customer.name }},\n\nThank you for your order {{ order.number }}, totaling {{ order.total }}.\n\nWe'll notify you as it progresses.\n\n{{ store.name }}"],
            ['event_key' => 'order.placed', 'channel' => 'sms', 'subject' => null, 'body' => 'Thanks {{ customer.name }}! Order {{ order.number }} confirmed. Total: {{ order.total }}. - {{ store.name }}'],
            ['event_key' => 'order.placed.staff', 'channel' => 'email', 'subject' => 'New Order Received - {{ order.number }}', 'body' => 'A new order {{ order.number }} was placed by {{ customer.name }} for {{ order.total }}.'],
            ['event_key' => 'order.status_changed', 'channel' => 'email', 'subject' => 'Order {{ order.number }} Update', 'body' => 'Hi {{ customer.name }}, your order {{ order.number }} is now {{ order.to_status }}.'],
            ['event_key' => 'order.cancelled', 'channel' => 'email', 'subject' => 'Order {{ order.number }} Cancelled', 'body' => 'Hi {{ customer.name }}, your order {{ order.number }} has been cancelled.'],
            ['event_key' => 'payment.recorded', 'channel' => 'email', 'subject' => 'Payment Received - {{ order.number }}', 'body' => "We've received your payment of {{ order.payment_amount }} for order {{ order.number }}. Thank you!"],
            ...ReminderTemplateDefaults::definitions(),
        ];

        foreach ($defaults as $default) {
            NotificationTemplate::query()->create([...$default, 'tenant_id' => $tenant->id, 'is_active' => true]);
        }
    }
}
