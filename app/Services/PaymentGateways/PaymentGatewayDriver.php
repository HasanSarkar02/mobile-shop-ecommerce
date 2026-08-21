<?php

declare(strict_types=1);

namespace App\Services\PaymentGateways;

use App\Models\Order;
use App\Support\PaymentValidationResult;

interface PaymentGatewayDriver
{
    public function initiate(Order $order, string $tranId, string $successUrl, string $failUrl, string $cancelUrl, string $ipnUrl): string;

    public function validateTransaction(string $valId): PaymentValidationResult;
}
