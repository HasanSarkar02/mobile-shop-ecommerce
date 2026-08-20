<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Support\Tenancy\TenantUrlGenerator;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlanChangeApprovedNotification extends Notification implements ShouldQueue
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

        $subscription = TenantSubscription::query()->where('tenant_id', $tenant->id)->first();
        $currentPlanName = $subscription?->plan instanceof Plan ? $subscription->plan->name : 'None';

        $message = (new MailMessage)
            ->subject('Your plan change request was approved')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('Your request to move "'.$tenant->name.'" to the "'.$requestedPlan->name.'" plan has been approved.')
            ->line('Your store is now on the "'.$currentPlanName.'" plan.');

        if ($reviewer !== null) {
            $message->line('Reviewed by: '.$reviewer->name);
        }

        $periodEndsAt = $subscription?->getAttribute('current_period_ends_at');

        if ($periodEndsAt instanceof CarbonInterface) {
            $message->line('Your current plan period ends on '.$periodEndsAt->format('F j, Y').'.');
        }

        return $message->action('Open Billing', app(TenantUrlGenerator::class)->admin($tenant));
    }
}
