<?php

declare(strict_types=1);

namespace App\Enums;

enum TombstoneReason: string
{
    case TrialZeroOrders = 'trial_0_orders';
    case Active30d = 'active_30d';
    case Abuse = 'abuse';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::TrialZeroOrders => 'Trial (0 orders) — 0-day quarantine',
            self::Active30d => 'Active/used — 30-day quarantine',
            self::Abuse => 'Abuse/spam — permanent',
            self::Rejected => 'Rejected pending approval',
        };
    }

    public function quarantineDays(): ?int
    {
        return match ($this) {
            self::TrialZeroOrders => 0,
            self::Active30d => 30,
            self::Abuse => null, // permanent
            self::Rejected => 0,
        };
    }
}
