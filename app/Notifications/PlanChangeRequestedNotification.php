<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Tenant;
use App\Support\Tenancy\TenantUrlGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlanChangeRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly PlanChangeRequest $request) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $tenant = Tenant::query()->findOrFail($this->request->getAttribute('tenant_id'));
        $plan = Plan::query()->findOrFail($this->request->getAttribute('requested_plan_id'));

        return (new MailMessage)
            ->subject('New plan upgrade request')
            ->line('Tenant "'.$tenant->name.'" has requested to move to the "'.$plan->name.'" plan.')
            ->action(
                'Review Request',
                app(TenantUrlGenerator::class)->platform('/platform/plan-change-requests'),
            );
    }
}
