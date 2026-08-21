<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Enums\OrderPaymentStatus;
use App\Models\Order;
use App\Models\OrderFulfillment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PathaoDriver implements CourierDriver
{
    public function createShipment(Order $order, OrderFulfillment $fulfillment, array $credentials, string $baseUrl): ShipmentResult
    {
        $base = rtrim($baseUrl ?: 'https://courier-api.pathao.com', '/');
        $token = $this->ensureToken($credentials, $base);

        $storeId = $credentials['store_id'] ?? null;
        if (! $storeId) {
            $storeId = $this->fetchDefaultStoreId($token, $base);
        }

        $shipping = $order->shipping_address_snapshot ?? [];
        $amount = $this->amountToCollect($order);

        $payload = [
            'store_id' => (int) $storeId,
            'merchant_order_id' => $order->order_number.'-'.$fulfillment->id,
            'recipient_name' => $shipping['recipient_name'] ?? $order->customerDisplayName(),
            'recipient_phone' => $shipping['phone'] ?? $order->guest_phone ?? '01700000000',
            'recipient_address' => $this->formatAddress($shipping),
            'delivery_type' => 48,
            'item_type' => 2,
            'item_quantity' => $order->items()->sum('quantity'),
            'item_weight' => '0.5',
            'amount_to_collect' => (int) $amount,
            'item_description' => $order->items->pluck('product_name_snapshot')->implode(', '),
        ];

        $response = Http::withToken($token)->post($base.'/aladdin/api/v1/orders', $payload);

        if (! $response->successful()) {
            Log::warning('Pathao createShipment failed', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Pathao API error: '.$response->body());
        }

        $json = $response->json();
        $data = $json['data'] ?? $json;

        return new ShipmentResult(
            consignmentId: (string) ($data['consignment_id'] ?? ''),
            trackingCode: (string) ($data['consignment_id'] ?? ''),
            status: (string) ($data['order_status'] ?? 'Pending'),
            rawStatus: (string) ($data['order_status'] ?? ''),
            raw: $json,
        );
    }

    public function createBulk(array $orders, array $credentials, string $baseUrl): BulkShipmentResult
    {
        $base = rtrim($baseUrl ?: 'https://courier-api.pathao.com', '/');
        $token = $this->ensureToken($credentials, $base);

        $storeId = $credentials['store_id'] ?? $this->fetchDefaultStoreId($token, $base);
        $payloadOrders = [];

        foreach ($orders as $order) {
            $fulfillment = $order->fulfillments->first();
            $shipping = $order->shipping_address_snapshot ?? [];
            $payloadOrders[] = [
                'store_id' => (int) $storeId,
                'merchant_order_id' => $order->order_number.($fulfillment ? '-'.$fulfillment->id : ''),
                'recipient_name' => $shipping['recipient_name'] ?? $order->customerDisplayName(),
                'recipient_phone' => $shipping['phone'] ?? $order->guest_phone ?? '01700000000',
                'recipient_address' => $this->formatAddress($shipping),
                'delivery_type' => 48,
                'item_type' => 2,
                'item_quantity' => $order->items()->sum('quantity'),
                'item_weight' => '0.5',
                'amount_to_collect' => (int) $this->amountToCollect($order),
                'item_description' => $order->items->pluck('product_name_snapshot')->implode(', '),
            ];
        }

        $response = Http::withToken($token)->post($base.'/aladdin/api/v1/orders/bulk', ['orders' => $payloadOrders]);

        if (! $response->successful()) {
            throw new \RuntimeException('Pathao bulk error: '.$response->body());
        }

        $json = $response->json();

        return new BulkShipmentResult(items: [['invoice' => 'bulk', 'status' => $json['type'] ?? 'success', 'consignment_id' => null, 'tracking_code' => null, 'error' => null]]);
    }

    public function fetchStatus(string $trackingCodeOrInvoice, array $credentials, string $baseUrl): ShipmentStatus
    {
        $base = rtrim($baseUrl ?: 'https://courier-api.pathao.com', '/');
        $token = $this->ensureToken($credentials, $base);

        $response = Http::withToken($token)->get($base."/aladdin/api/v1/orders/{$trackingCodeOrInvoice}/info");

        if (! $response->successful()) {
            return new ShipmentStatus(status: 'unknown', rawStatus: 'unknown', raw: []);
        }

        $json = $response->json();
        $raw = $json['data']['order_status_slug'] ?? $json['data']['order_status'] ?? 'unknown';

        return new ShipmentStatus(status: $this->mapStatus((string) $raw), rawStatus: (string) $raw, raw: $json);
    }

    public function fetchBalance(array $credentials, string $baseUrl): float
    {
        return 0.0;
    }

    private function ensureToken(array $credentials, string $base): string
    {
        $accessToken = $credentials['access_token'] ?? null;
        $expiresAt = $credentials['expires_at'] ?? null;

        if ($accessToken && $expiresAt && now()->lt(Carbon::parse($expiresAt))) {
            return $accessToken;
        }

        $clientId = $credentials['client_id'] ?? null;
        $clientSecret = $credentials['client_secret'] ?? null;
        $username = $credentials['username'] ?? null;
        $password = $credentials['password'] ?? null;
        $refreshToken = $credentials['refresh_token'] ?? null;

        if ($refreshToken && $clientId && $clientSecret) {
            $resp = Http::post($base.'/aladdin/api/v1/issue-token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

            if ($resp->successful()) {
                return $resp->json('access_token');
            }
        }

        if (! $clientId || ! $clientSecret || ! $username || ! $password) {
            throw new \InvalidArgumentException('Pathao credentials missing client_id/secret and username/password.');
        }

        $resp = Http::post($base.'/aladdin/api/v1/issue-token', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'password',
            'username' => $username,
            'password' => $password,
        ]);

        if (! $resp->successful()) {
            throw new \RuntimeException('Pathao token issue failed: '.$resp->body());
        }

        return $resp->json('access_token') ?? throw new \RuntimeException('Pathao token missing in response.');
    }

    private function fetchDefaultStoreId(string $token, string $base): ?int
    {
        $resp = Http::withToken($token)->get($base.'/aladdin/api/v1/stores');

        if (! $resp->successful()) {
            return null;
        }

        $data = $resp->json('data.data') ?? $resp->json('data') ?? [];

        return $data[0]['store_id'] ?? null;
    }

    private function amountToCollect(Order $order): int|float
    {
        $paid = $order->payments()->where('status', OrderPaymentStatus::Paid)->sum('amount');
        $due = max(0, (int) $order->grand_total - (int) $paid);

        return $due / 100;
    }

    private function formatAddress(array $shipping): string
    {
        $parts = array_filter([$shipping['address_line_1'] ?? null, $shipping['address_line_2'] ?? null, $shipping['city'] ?? null, $shipping['area'] ?? null]);

        return $parts ? implode(', ', $parts) : 'N/A';
    }

    private function mapStatus(string $raw): string
    {
        return match (strtolower($raw)) {
            'delivered' => 'delivered',
            'cancelled', 'canceled' => 'cancelled',
            'pending' => 'pending',
            default => strtolower($raw),
        };
    }
}
