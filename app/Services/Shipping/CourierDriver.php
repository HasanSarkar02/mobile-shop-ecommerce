<?php

declare(strict_types=1);

namespace App\Services\Shipping;

use App\Models\Order;
use App\Models\OrderFulfillment;

final class ShipmentResult
{
    public function __construct(
        public readonly string $consignmentId,
        public readonly string $trackingCode,
        public readonly string $status,
        public readonly ?string $rawStatus = null,
        public readonly array $raw = [],
    ) {}
}

final class BulkShipmentResult
{
    /** @param array<int, array{invoice:string, status:string, consignment_id:?string, tracking_code:?string, error:?string}> $items */
    public function __construct(
        public readonly array $items = [],
    ) {}
}

final class ShipmentStatus
{
    public function __construct(
        public readonly string $status,
        public readonly string $rawStatus,
        public readonly array $raw = [],
    ) {}
}

interface CourierDriver
{
    public function createShipment(Order $order, OrderFulfillment $fulfillment, array $credentials, string $baseUrl): ShipmentResult;

    /** @param array<int, Order> $orders */
    public function createBulk(array $orders, array $credentials, string $baseUrl): BulkShipmentResult;

    public function fetchStatus(string $trackingCodeOrInvoice, array $credentials, string $baseUrl): ShipmentStatus;

    public function fetchBalance(array $credentials, string $baseUrl): float;
}
