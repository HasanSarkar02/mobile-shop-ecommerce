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
        public readonly mixed $coupon = null,
    ) {}

    public static function valid(int $discountAmount, bool $freeShipping = false, mixed $coupon = null): self
    {
        return new self(true, $discountAmount, $freeShipping, null, $coupon);
    }

    public static function invalid(string $message): self
    {
        return new self(false, 0, false, $message, null);
    }

    public static function none(): self
    {
        return new self(true, 0, false, null, null);
    }

    public function isAutomatic(): bool
    {
        return $this->valid && $this->coupon !== null && empty($this->coupon->code);
    }
}
