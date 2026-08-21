<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Enums\OrderPaymentStatus;
use App\Models\Order;
use App\Models\OrderFulfillment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SteadfastDriver implements CourierDriver
{
    public function createShipment(Order $order, OrderFulfillment $fulfillment, array $credentials, string $baseUrl): ShipmentResult
    {
        $apiKey = $credentials['api_key'] ?? $credentials['Api-Key'] ?? null;
        $secretKey = $credentials['secret_key'] ?? $credentials['Secret-Key'] ?? null;

        if (! $apiKey || ! $secretKey) {
            throw new \InvalidArgumentException('Steadfast credentials missing api_key/secret_key.');
        }

        $base = rtrim($baseUrl ?: 'https://portal.packzy.com/api/v1', '/');
        $shipping = $order->shipping_address_snapshot ?? [];
        $amount = $this->codAmount($order, $fulfillment);

        $payload = [
            'invoice' => $order->order_number.'-'.$fulfillment->id,
            'recipient_name' => $shipping['recipient_name'] ?? $order->customerDisplayName(),
            'recipient_phone' => $shipping['phone'] ?? $order->guest_phone ?? '01700000000',
            'recipient_address' => $this->formatAddress($shipping),
            'cod_amount' => $amount,
            'note' => $order->customer_note ?? '',
        ];

        $response = Http::withHeaders([
            'Api-Key' => $apiKey,
            'Secret-Key' => $secretKey,
            'Content-Type' => 'application/json',
        ])->post($base.'/create_order', $payload);

        if (! $response->successful()) {
            Log::warning('Steadfast createShipment failed', ['status' => $response->status(), 'body' => $response->body(), 'order' => $order->id]);
            throw new \RuntimeException('Steadfast API error: '.$response->body());
        }

        $json = $response->json();

        if (($json['status'] ?? null) !== 200) {
            throw new \RuntimeException($json['message'] ?? 'Steadfast failed to create consignment.');
        }

        $consignment = $json['consignment'] ?? $json;

        return new ShipmentResult(
            consignmentId: (string) ($consignment['consignment_id'] ?? ''),
            trackingCode: (string) ($consignment['tracking_code'] ?? $consignment['consignment_id'] ?? ''),
            status: (string) ($consignment['status'] ?? 'in_review'),
            rawStatus: (string) ($consignment['status'] ?? ''),
            raw: $json,
        );
    }

    public function createBulk(array $orders, array $credentials, string $baseUrl): BulkShipmentResult
    {
        $apiKey = $credentials['api_key'] ?? $credentials['Api-Key'] ?? null;
        $secretKey = $credentials['secret_key'] ?? $credentials['Secret-Key'] ?? null;
        $base = rtrim($baseUrl ?: 'https://portal.packzy.com/api/v1', '/');

        $data = [];
        foreach ($orders as $order) {
            $fulfillment = $order->fulfillments->first();
            $shipping = $order->shipping_address_snapshot ?? [];
            $data[] = [
                'invoice' => $order->order_number.($fulfillment ? '-'.$fulfillment->id : ''),
                'recipient_name' => $shipping['recipient_name'] ?? $order->customerDisplayName(),
                'recipient_phone' => $shipping['phone'] ?? $order->guest_phone ?? '01700000000',
                'recipient_address' => $this->formatAddress($shipping),
                'cod_amount' => $this->codAmount($order, $fulfillment),
                'note' => $order->customer_note ?? '',
            ];
        }

        $response = Http::withHeaders([
            'Api-Key' => $apiKey,
            'Secret-Key' => $secretKey,
            'Content-Type' => 'application/json',
        ])->post($base.'/create_order/bulk-order', ['data' => json_encode($data)]);

        $json = $response->json();
        $items = [];

        if (isset($json['data']) && is_array($json['data'])) {
            foreach ($json['data'] as $row) {
                $items[] = [
                    'invoice' => $row['invoice'] ?? '',
                    'status' => $row['status'] ?? 'error',
                    'consignment_id' => $row['consignment_id'] ?? null,
                    'tracking_code' => $row['tracking_code'] ?? null,
                    'error' => ($row['status'] ?? '') === 'error' ? json_encode($row) : null,
                ];
            }
        }

        return new BulkShipmentResult(items: $items);
    }

    public function fetchStatus(string $trackingCodeOrInvoice, array $credentials, string $baseUrl): ShipmentStatus
    {
        $apiKey = $credentials['api_key'] ?? $credentials['Api-Key'] ?? null;
        $secretKey = $credentials['secret_key'] ?? $credentials['Secret-Key'] ?? null;
        $base = rtrim($baseUrl ?: 'https://portal.packzy.com/api/v1', '/');

        $paths = ["/status_by_trackingcode/{$trackingCodeOrInvoice}", "/status_by_invoice/{$trackingCodeOrInvoice}", "/status_by_cid/{$trackingCodeOrInvoice}"];

        foreach ($paths as $path) {
            $response = Http::withHeaders(['Api-Key' => $apiKey, 'Secret-Key' => $secretKey])->get($base.$path);
            if ($response->successful()) {
                $json = $response->json();
                $status = $json['delivery_status'] ?? $json['status'] ?? 'unknown';

                return new ShipmentStatus(status: $this->mapStatus((string) $status), rawStatus: (string) $status, raw: $json);
            }
        }

        return new ShipmentStatus(status: 'unknown', rawStatus: 'unknown', raw: []);
    }

    public function fetchBalance(array $credentials, string $baseUrl): float
    {
        $apiKey = $credentials['api_key'] ?? $credentials['Api-Key'] ?? null;
        $secretKey = $credentials['secret_key'] ?? $credentials['Secret-Key'] ?? null;
        $base = rtrim($baseUrl ?: 'https://portal.packzy.com/api/v1', '/');

        $response = Http::withHeaders(['Api-Key' => $apiKey, 'Secret-Key' => $secretKey])->get($base.'/get_balance');

        if (! $response->successful()) {
            throw new \RuntimeException('Failed to fetch Steadfast balance: '.$response->body());
        }

        return (float) ($response->json('current_balance') ?? 0);
    }

    private function codAmount(Order $order, ?OrderFulfillment $fulfillment): int|float
    {
        $paid = $order->payments()->where('status', OrderPaymentStatus::Paid)->sum('amount');
        $due = max(0, (int) $order->grand_total - (int) $paid);

        return $due / 100;
    }

    private function formatAddress(array $shipping): string
    {
        $parts = array_filter([$shipping['address_line_1'] ?? null, $shipping['address_line_2'] ?? null, $shipping['city'] ?? null, $shipping['area'] ?? null, $shipping['postal_code'] ?? null]);

        return $parts ? implode(', ', $parts) : 'N/A';
    }

    private function mapStatus(string $raw): string
    {
        return match (strtolower($raw)) {
            'delivered' => 'delivered',
            'partial_delivered' => 'delivered',
            'cancelled' => 'cancelled',
            'hold' => 'hold',
            'in_review', 'pending' => 'pending',
            default => strtolower($raw),
        };
    }
}
