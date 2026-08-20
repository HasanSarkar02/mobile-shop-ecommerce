<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Support\Tenancy\TenantUrlGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlanChangeRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly PlanChangeRequest $request) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $request = PlanChangeRequest::query()
            ->withoutGlobalScope('tenant')
            ->whereKey($this->request->getKey())
            ->firstOrFail();

        $tenant = Tenant::query()->findOrFail((int) $request->getAttribute('tenant_id'));
        $requestedPlan = Plan::query()->findOrFail((int) $request->getAttribute('requested_plan_id'));
        $reviewerId = $request->getAttribute('reviewed_by_user_id');
        $reviewer = is_numeric($reviewerId) ? User::query()->find((int) $reviewerId) : null;
        $rejectionReason = $request->getAttribute('rejection_reason');

        $subscription = TenantSubscription::query()->where('tenant_id', $tenant->id)->first();
        $currentPlanName = $subscription?->plan instanceof Plan ? $subscription->plan->name : 'None';

        $message = (new MailMessage)
            ->subject('Your plan change request was declined')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('Your request to move "'.$tenant->name.'" to the "'.$requestedPlan->name.'" plan has been declined.')
            ->line('Your store remains on the "'.$currentPlanName.'" plan.');

        if (is_string($rejectionReason) && trim($rejectionReason) !== '') {
            $message->line('Reason: '.$rejectionReason);
        }

        if ($reviewer !== null) {
            $message->line('Reviewed by: '.$reviewer->name);
        }

        return $message->action('Open Billing', app(TenantUrlGenerator::class)->admin($tenant));
    }
}
