<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use App\Support\Tenancy\TenantUrlGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShopApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Tenant $tenant) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Great news — your shop "'.$this->tenant->name.'" has been approved')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('Your shop "'.$this->tenant->name.'" has been approved and your free trial has started.')
            ->action('Go to your Admin Panel', app(TenantUrlGenerator::class)->admin($this->tenant));
    }
}
