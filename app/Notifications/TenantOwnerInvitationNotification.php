<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use App\Support\Tenancy\TenantUrlGenerator;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantOwnerInvitationNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        public readonly string $setupToken,
        public readonly CarbonInterface $expiresAt,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $setupUrl = app(TenantUrlGenerator::class)->canonicalRoute(
            $this->tenant,
            'storefront.owner-invitation.show',
            ['token' => $this->setupToken],
        );

        return (new MailMessage)
            ->subject('Your store "'.$this->tenant->name.'" is ready')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('A store has been created for you. Use the secure setup link below to create your admin password.')
            ->line('This setup link expires on '.$this->expiresAt->toDateTimeString().'.')
            ->action('Set up your Admin Panel', $setupUrl);
    }
}
