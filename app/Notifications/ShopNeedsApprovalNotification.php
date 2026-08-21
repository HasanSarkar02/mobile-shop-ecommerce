<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantUrlGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShopNeedsApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Tenant $tenant) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $owner = User::query()
            ->where('tenant_id', $this->tenant->getKey())
            ->where('role', 'owner')
            ->orderBy('id')
            ->first();

        $message = (new MailMessage)
            ->subject('New shop awaiting approval: '.$this->tenant->name)
            ->line('A new shop "'.$this->tenant->name.'" ('.$this->tenant->subdomain.') has been created and is waiting for approval.')
            ->action(
                'Review Shop',
                app(TenantUrlGenerator::class)->platform('/platform/tenants/'.$this->tenant->getKey()),
            );

        if ($owner !== null) {
            $message->line('Owner: '.$owner->name.' ('.$owner->email.')');

            if (is_string($owner->phone) && $owner->phone !== '') {
                $message->line('Owner mobile: '.$owner->phone);
            }
        }

        return $message;
    }
}
