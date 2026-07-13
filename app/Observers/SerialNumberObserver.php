<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\SerialNumber;
use App\Models\StockItem;
use App\Services\InventoryService;

class SerialNumberObserver
{
    public function saved(SerialNumber $serial): void
    {
        $locationId = $serial->location_id ?? app(InventoryService::class)->defaultLocation()->id;

        $count = SerialNumber::query()
            ->where('product_variant_id', $serial->product_variant_id)
            ->where('status', 'available')
            ->count();

        StockItem::query()
            ->where('product_variant_id', $serial->product_variant_id)
            ->where('location_id', $locationId)
            ->update(['quantity' => $count]);
    }
}