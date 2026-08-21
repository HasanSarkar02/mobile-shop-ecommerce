<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Enums\OrderEventType;
use App\Enums\OrderFulfillmentStatus;
use App\Models\CourierConnection;
use App\Models\CourierProvider;
use App\Models\Order;
use App\Models\OrderFulfillment;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;

class CourierService
{
    public function sendFulfillment(Order $order, OrderFulfillment $fulfillment, CourierConnection $connection): ShipmentResult
    {
        abort_unless((int) $connection->tenant_id === (int) $order->tenant_id, 403);
        abort_unless((int) $fulfillment->order_id === (int) $order->id, 422);

        $provider = $connection->provider()->first() ?? CourierProvider::query()->findOrFail($connection->courier_provider_id);
        $baseUrl = $provider->effectiveBaseUrl((bool) $connection->sandbox) ?: $connection->effectiveBaseUrl();
        $driverClass = $provider->driver_class ?? config('couriers.drivers.'.$provider->code);

        abort_unless($driverClass && class_exists($driverClass), 404, 'Courier driver not configured.');

        $driver = app($driverClass);
        $credentials = $connection->credentials ?? [];

        $result = $driver->createShipment($order, $fulfillment, $credentials, $baseUrl);

        DB::transaction(function () use ($fulfillment, $result, $provider): void {
            $fulfillment->update([
                'tracking_number' => $result->trackingCode ?: $fulfillment->tracking_number,
                'courier_name' => $provider->displayName(),
            ]);

            // Optionally store consignment id in tracking_number if empty, else keep both
            if ($result->consignmentId && $fulfillment->tracking_number === $result->trackingCode) {
                // keep as is
            }
        });

        $order->events()->create([
            'tenant_id' => $order->tenant_id,
            'type' => OrderEventType::FulfillmentUpdated,
            'description' => 'Courier shipment created via '.$provider->displayName().' — tracking '.$result->trackingCode.' ('.$result->status.')',
            'metadata' => ['provider' => $provider->code, 'consignment_id' => $result->consignmentId, 'tracking_code' => $result->trackingCode, 'status' => $result->status, 'fulfillment_id' => $fulfillment->id],
            'created_by' => auth()->id(),
        ]);

        return $result;
    }

    public function syncStatus(OrderFulfillment $fulfillment, CourierConnection $connection): ShipmentStatus
    {
        $provider = $connection->provider()->first() ?? CourierProvider::query()->findOrFail($connection->courier_provider_id);
        $baseUrl = $provider->effectiveBaseUrl((bool) $connection->sandbox) ?: $connection->effectiveBaseUrl();
        $driverClass = $provider->driver_class ?? config('couriers.drivers.'.$provider->code);
        $driver = app($driverClass);

        $tracking = $fulfillment->tracking_number;

        if (! $tracking) {
            throw new \RuntimeException('No tracking number for this fulfillment.');
        }

        $status = $driver->fetchStatus($tracking, $connection->credentials ?? [], $baseUrl);

        $mapped = $this->mapToFulfillmentStatus($status->status);

        if ($mapped) {
            app(OrderService::class)->updateFulfillment($fulfillment, $mapped);
        }

        return $status;
    }

    private function mapToFulfillmentStatus(string $courierStatus): ?OrderFulfillmentStatus
    {
        return match (strtolower($courierStatus)) {
            'delivered', 'delivered_approval_pending' => OrderFulfillmentStatus::Delivered,
            'cancelled', 'cancelled_approval_pending' => OrderFulfillmentStatus::Failed,
            'shipped' => OrderFulfillmentStatus::Shipped,
            'packed' => OrderFulfillmentStatus::Packed,
            'pending', 'in_review', 'hold' => OrderFulfillmentStatus::Pending,
            default => null,
        };
    }
}
