<?php

declare(strict_types=1);

namespace App\Filament\Store\Pages;

use App\Enums\PlanChangeRequestStatus;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Product;
use App\Models\User;
use App\Notifications\PlanChangeRequestedNotification;
use App\Services\SubscriptionService;
use BackedEnum;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class BillingPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Billing & Plan';

    protected string $view = 'filament.store.pages.billing';

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->isOwner() ?? false;
    }

    public function getSubscription()
    {
        return tenant()->subscription;
    }

    public function getLatestPlanChangeRequest()
    {
        return PlanChangeRequest::query()
            ->whereIn('status', [PlanChangeRequestStatus::Pending, PlanChangeRequestStatus::Rejected])
            ->latest()
            ->first();
    }

    public function getPlans()
    {
        return Plan::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    public function getProductUsage(): int
    {
        return Product::query()->count();
    }

    public function getStaffUsage(): int
    {
        return User::query()->where('tenant_id', tenant()->id)->where('role', 'staff')->count();
    }

    public function requestPlan(int $planId): void
    {
        $plan = Plan::query()->whereKey($planId)->where('is_active', true)->first();

        if ($plan === null) {
            Notification::make()->title('This plan is no longer available.')->danger()->send();

            return;
        }

        try {
            app(SubscriptionService::class)->assertCanRequestPlanChange(tenant(), $plan);
        } catch (DomainException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        $request = PlanChangeRequest::query()->create([
            'tenant_id' => tenant()->id,
            'requested_plan_id' => $plan->id,
            'status' => PlanChangeRequestStatus::Pending,
        ]);

        User::query()->where('is_platform_admin', true)->get()->each(
            fn (User $admin) => $admin->notify(new PlanChangeRequestedNotification($request))
        );

        Notification::make()->title('Upgrade request sent — our team will follow up shortly.')->success()->send();
    }
}
