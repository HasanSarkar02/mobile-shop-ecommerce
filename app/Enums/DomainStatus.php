<?php

declare(strict_types=1);

namespace App\Enums;

enum DomainStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Active = 'active';
    case Failed = 'failed';
    case Suspended = 'suspended';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending verification',
            self::Verified => 'Verified',
            self::Active => 'Active',
            self::Failed => 'Verification failed',
            self::Suspended => 'Suspended',
            self::Revoked => 'Revoked',
        };
    }

    public function canTransitionTo(self $status): bool
    {
        if ($this === $status) {
            return true;
        }

        return match ($this) {
            self::Pending => in_array($status, [self::Verified, self::Failed, self::Revoked], true),
            self::Verified => in_array($status, [self::Pending, self::Active, self::Revoked], true),
            self::Active => in_array($status, [self::Suspended, self::Revoked], true),
            self::Failed => in_array($status, [self::Pending, self::Verified, self::Revoked], true),
            self::Suspended => in_array($status, [self::Active, self::Revoked], true),
            self::Revoked => false,
        };
    }
}
