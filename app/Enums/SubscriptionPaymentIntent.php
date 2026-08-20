<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionPaymentIntent: string
{
    case AssignPlan = 'assign_plan';
    case ExtendSubscription = 'extend_subscription';

    public function label(): string
    {
        return match ($this) {
            self::AssignPlan => 'Assign Plan',
            self::ExtendSubscription => 'Extend Subscription',
        };
    }
}
