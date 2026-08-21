<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShopPendingApprovalNotification extends Notification implements ShouldQueue
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
            ->subject('Your shop "'.$this->tenant->name.'" is under review')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('Thanks for signing up! Your shop "'.$this->tenant->name.'" is now under review by our team.')
            ->line('You will receive an email as soon as your shop has been approved. This usually takes less than a day.');
    }
}
