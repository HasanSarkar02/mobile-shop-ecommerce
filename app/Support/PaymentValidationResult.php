<?php

declare(strict_types=1);

namespace App\Support;

final class PaymentValidationResult
{
    public function __construct(
        public readonly string $status, // 'valid' | 'failed' | 'cancelled'
        public readonly string $tranId,
        public readonly int $amount, // integer minor units
        public readonly ?string $cardType = null,
    ) {
    }
}