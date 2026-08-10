<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\PlanChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlanChangeRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly PlanChangeRequest $request)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New plan upgrade request')
            ->line('Tenant "'.$this->request->tenant->name.'" has requested to move to the "'.$this->request->requestedPlan->name.'" plan.')
            ->action('Review Request', url('/platform/plan-change-requests'));
    }
}