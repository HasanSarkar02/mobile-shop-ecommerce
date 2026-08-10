<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionEventType: string
{
    case TrialStarted = 'trial_started';
    case Upgraded = 'upgraded';
    case Downgraded = 'downgraded';
    case Renewed = 'renewed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Reactivated = 'reactivated';

    public function label(): string
    {
        return match ($this) {
            self::TrialStarted => 'Trial Started',
            self::Upgraded => 'Upgraded',
            self::Downgraded => 'Downgraded',
            self::Renewed => 'Renewed',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
            self::Reactivated => 'Reactivated',
        };
    }
}