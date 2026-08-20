<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Support\Tenancy\TenantUrlGenerator;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlatformAdminInvitationNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly CarbonInterface $expiresAt,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = app(TenantUrlGenerator::class)->platform('/platform-admin-invitation/'.$this->token);

        return (new MailMessage)
            ->subject('Your Platform Admin invitation')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('You have been invited to manage the MobileShop platform.')
            ->line('Create your password using the secure setup link below. It expires on '.$this->expiresAt->toDateTimeString().'.')
            ->action('Set up Platform Admin access', $url);
    }
}
