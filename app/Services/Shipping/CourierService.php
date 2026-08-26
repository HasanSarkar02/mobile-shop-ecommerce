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
use Illuminate\Support\Collection;
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

        DB::transaction(function () use ($fulfillment, $result, $provider, $connection): void {
            $fulfillment->update([
                'tracking_number' => $result->trackingCode ?: $fulfillment->tracking_number,
                'courier_name' => $provider->displayName(),
                // The FK, not the display string, is what later resolves this
                // consignment back to the credentials that created it. courier_name
                // stays because it is the historical label shown on the public
                // tracking page and must survive the connection being deleted.
                'courier_connection_id' => $connection->getKey(),
            ]);
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

    /**
     * Bulk equivalent of sendFulfillment(), and the only sanctioned bulk writer.
     *
     * The admin bulk action used to call `$driver->createBulk()` directly and
     * discard the response into notifications, so those consignments never got a
     * tracking_number — which made them invisible to the hourly poller (it gates
     * on `whereNotNull('tracking_number')`) and left them permanently unpollable.
     * Routing bulk through here means there is exactly one place that persists
     * courier results.
     *
     * Drivers echo back the `invoice` they were given, which is
     * "{order_number}-{fulfillment_id}", so that string is the join key. A driver
     * that cannot report per-consignment results (Pathao's bulk endpoint returns a
     * single aggregate row) simply matches nothing and is reported as unmatched
     * rather than being silently treated as success.
     *
     * @param  Collection<int, Order>|array<int, Order>  $orders
     * @return array{result: BulkShipmentResult, persisted: int, unmatched: array<int, string>}
     */
    public function sendFulfillmentsBulk(iterable $orders, CourierConnection $connection): array
    {
        $orders = collect($orders);

        foreach ($orders as $order) {
            abort_unless((int) $connection->tenant_id === (int) $order->tenant_id, 403);
        }

        $provider = $connection->provider()->first() ?? CourierProvider::query()->findOrFail($connection->courier_provider_id);
        $baseUrl = $provider->effectiveBaseUrl((bool) $connection->sandbox) ?: $connection->effectiveBaseUrl();
        $driverClass = $provider->driver_class ?? config('couriers.drivers.'.$provider->code);

        abort_unless($driverClass && class_exists($driverClass), 404, 'Courier driver not configured.');

        $orders->load('fulfillments');

        /** @var array<string, OrderFulfillment> $byInvoice */
        $byInvoice = [];

        foreach ($orders as $order) {
            foreach ($order->fulfillments as $fulfillment) {
                $byInvoice[$order->order_number.'-'.$fulfillment->getKey()] = $fulfillment;
            }
        }

        $result = app($driverClass)->createBulk($orders->all(), $connection->credentials ?? [], $baseUrl);

        $persisted = 0;
        $unmatched = [];

        foreach ($result->items as $item) {
            $invoice = (string) ($item['invoice'] ?? '');
            $tracking = $item['tracking_code'] ?? $item['consignment_id'] ?? null;
            $fulfillment = $byInvoice[$invoice] ?? null;

            if (! $fulfillment || ! $tracking) {
                $unmatched[] = $invoice !== '' ? $invoice : '(no invoice returned)';

                continue;
            }

            DB::transaction(function () use ($fulfillment, $tracking, $provider, $connection, $item): void {
                $fulfillment->update([
                    'tracking_number' => (string) $tracking,
                    'courier_name' => $provider->displayName(),
                    'courier_connection_id' => $connection->getKey(),
                ]);

                $order = $fulfillment->order()->firstOrFail();

                $order->events()->create([
                    'tenant_id' => $order->tenant_id,
                    'type' => OrderEventType::FulfillmentUpdated,
                    'description' => 'Courier shipment created in bulk via '.$provider->displayName().' — tracking '.$tracking,
                    'metadata' => [
                        'provider' => $provider->code,
                        'consignment_id' => $item['consignment_id'] ?? null,
                        'tracking_code' => (string) $tracking,
                        'status' => $item['status'] ?? null,
                        'fulfillment_id' => $fulfillment->getKey(),
                        'bulk' => true,
                    ],
                    'created_by' => auth()->id(),
                ]);
            });

            $persisted++;
        }

        return ['result' => $result, 'persisted' => $persisted, 'unmatched' => $unmatched];
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
