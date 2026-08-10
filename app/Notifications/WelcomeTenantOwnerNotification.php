<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeTenantOwnerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Tenant $tenant)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome — your store "'.$this->tenant->name.'" is ready')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('Your store has been created and is ready to set up.')
            ->action('Go to your Admin Panel', 'http://'.$this->tenant->subdomain.'.'.config('tenancy.central_domain').'/admin')
            ->line('Your trial ends on '.$this->tenant->trial_ends_at->format('F j, Y').'.');
    }
}