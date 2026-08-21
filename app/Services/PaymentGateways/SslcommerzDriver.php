<?php

declare(strict_types=1);

namespace App\Services\PaymentGateways;

use App\Models\Order;
use App\Support\PaymentValidationResult;
use Illuminate\Support\Facades\Http;

class SslcommerzDriver implements PaymentGatewayDriver
{
    private string $sessionUrl;

    private string $validatorUrl;

    public function __construct()
    {
        $sandbox = (bool) config('services.sslcommerz.sandbox', true);

        $this->sessionUrl = $sandbox
            ? 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php'
            : 'https://securepay.sslcommerz.com/gwprocess/v4/api.php';

        $this->validatorUrl = $sandbox
            ? 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php'
            : 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php';
    }

    public function initiate(Order $order, string $tranId, string $successUrl, string $failUrl, string $cancelUrl, string $ipnUrl): string
    {
        $response = Http::asForm()->post($this->sessionUrl, [
            'store_id' => config('services.sslcommerz.store_id'),
            'store_passwd' => config('services.sslcommerz.store_password'),
            'total_amount' => number_format($order->grand_total / 100, 2, '.', ''),
            'currency' => $order->currency_code,
            'tran_id' => $tranId,
            'success_url' => $successUrl,
            'fail_url' => $failUrl,
            'cancel_url' => $cancelUrl,
            'ipn_url' => $ipnUrl,
            'cus_name' => $order->customerDisplayName(),
            'cus_email' => $order->customer?->email ?? $order->guest_email ?? 'customer@example.com',
            'cus_phone' => $order->customer?->phone ?? $order->guest_phone ?? '01700000000',
            'cus_add1' => 'N/A',
            'cus_city' => 'Dhaka',
            'cus_country' => 'Bangladesh',
            'shipping_method' => 'NO',
            'product_name' => 'Order '.$order->order_number,
            'product_category' => 'General',
            'product_profile' => 'general',
        ])->json();

        if (($response['status'] ?? null) !== 'SUCCESS') {
            throw new \RuntimeException($response['failedreason'] ?? 'Failed to initiate payment session.');
        }

        return $response['GatewayPageURL'];
    }

    public function validateTransaction(string $valId): PaymentValidationResult
    {
        $response = Http::get($this->validatorUrl, [
            'val_id' => $valId,
            'store_id' => config('services.sslcommerz.store_id'),
            'store_passwd' => config('services.sslcommerz.store_password'),
            'v' => 1,
            'format' => 'json',
        ])->json();

        $status = match ($response['status'] ?? null) {
            'VALID', 'VALIDATED' => 'valid',
            'CANCELLED' => 'cancelled',
            default => 'failed',
        };

        return new PaymentValidationResult(
            status: $status,
            tranId: $response['tran_id'] ?? '',
            amount: isset($response['amount']) ? (int) round(((float) $response['amount']) * 100) : 0,
            cardType: $response['card_type'] ?? null,
        );
    }
}
