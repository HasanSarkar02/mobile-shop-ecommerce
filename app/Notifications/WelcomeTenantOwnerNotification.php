<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Support\Tenancy\TenantUrlGenerator;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeTenantOwnerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Tenant $tenant) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Welcome — your store "'.$this->tenant->name.'" is ready')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('Your store has been created and is ready to set up.')
            ->action('Go to your Admin Panel', app(TenantUrlGenerator::class)->admin($this->tenant));

        $subscription = TenantSubscription::query()
            ->where('tenant_id', $this->tenant->getKey())
            ->first();

        $periodEndsAt = $subscription?->getAttribute('current_period_ends_at');

        if ($periodEndsAt instanceof CarbonInterface) {
            $message->line('Your current plan period ends on '.$periodEndsAt->format('F j, Y').'.');
        }

        return $message;
    }
}
