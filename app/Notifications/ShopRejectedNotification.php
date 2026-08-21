<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShopRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly ?string $reason = null,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Update on your shop application for "'.$this->tenant->name.'"')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('We are sorry, but your shop application for "'.$this->tenant->name.'" was not approved.');

        if (is_string($this->reason) && trim($this->reason) !== '') {
            $message->line('Reason: '.trim($this->reason));
        }

        return $message;
    }
}
