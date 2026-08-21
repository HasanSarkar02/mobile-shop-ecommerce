<?php

declare(strict_types=1);

namespace App\Support;

final class CouponValidationResult
{
    private function __construct(
        public readonly bool $valid,
        public readonly int $discountAmount = 0,
        public readonly bool $freeShipping = false,
        public readonly ?string $message = null,
    ) {}

    public static function valid(int $discountAmount, bool $freeShipping = false): self
    {
        return new self(true, $discountAmount, $freeShipping);
    }

    public static function invalid(string $message): self
    {
        return new self(false, 0, false, $message);
    }

    public static function none(): self
    {
        return new self(true, 0, false);
    }
}
