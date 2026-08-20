<?php

declare(strict_types=1);

use App\Enums\OrderEventType;
use App\Enums\StockMovementType;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;

function renderOrderViewPartial(string $view, mixed $state): string
{
    return view($view, ['getState' => fn (): mixed => $state])->render();
}

it('renders inventory movements as separated table columns with a separate note', function (): void {
    $movement = new StockMovement([
        'type' => StockMovementType::Sale,
        'quantity_change' => -1,
        'quantity_after' => 4,
    ]);
    $movement->setAttribute('created_at', Carbon::parse('2026-08-20 10:31:00'));
    $movement->setRelation('variant', new ProductVariant(['sku' => 'PHONE-IMEI-LONG-SKU']));

    $html = renderOrderViewPartial(
        'filament.store.resources.order-resource.order-inventory',
        collect([$movement]),
    );

    expect($html)
        ->toContain('fi-order-inventory-table')
        ->toContain('table-layout: fixed')
        ->toContain('Date')
        ->toContain('Qty Change')
        ->toContain('Qty After')
        ->toContain('Details')
        ->toContain('PHONE-IMEI-LONG-SKU')
        ->toContain('Movement-level inventory history for this order')
        ->toContain('data-slot="icon"');
});

it('renders order items with deterministic image and column sizing', function (): void {
    $item = new OrderItem([
        'product_name_snapshot' => 'Honor 600 Pro 5G',
        'variant_sku_snapshot' => 'HN86881',
        'quantity' => 1,
        'unit_price' => 7500000,
        'line_total' => 7500000,
    ]);
    $item->setRelation('variant', new ProductVariant);

    $html = renderOrderViewPartial(
        'filament.store.resources.order-resource.order-items',
        collect([$item]),
    );

    expect($html)
        ->toContain('fi-order-items-table')
        ->toContain('fi-order-items-product')
        ->toContain('width: 48px')
        ->toContain('Unit Price')
        ->toContain('Total');
});

it('renders inventory and timeline empty states', function (): void {
    $inventory = renderOrderViewPartial(
        'filament.store.resources.order-resource.order-inventory',
        collect(),
    );
    $timeline = renderOrderViewPartial(
        'filament.store.resources.order-resource.order-timeline',
        collect(),
    );

    expect($inventory)->toContain('No inventory movements recorded.')->not->toContain('<table');
    expect($timeline)->toContain('No activity recorded yet.')->not->toContain('<ol');
});

it('renders timeline events with separate title, timestamp, actor, and description', function (): void {
    $event = new OrderEvent([
        'type' => OrderEventType::ItemAdded,
        'description' => 'Added 1 × PHONE-IMEI-LONG-SKU.',
    ]);
    $event->setAttribute('created_at', Carbon::parse('2026-08-20 10:31:00'));
    $event->setRelation('actor', new User(['name' => 'Demo Owner']));

    $html = renderOrderViewPartial(
        'filament.store.resources.order-resource.order-timeline',
        collect([$event]),
    );

    expect($html)
        ->toContain('Item Added')
        ->toContain('Aug 20, 2026, 10:31 AM')
        ->toContain('Demo Owner')
        ->toContain('Added 1 × PHONE-IMEI-LONG-SKU.')
        ->toContain('fi-order-timeline-card');
});
