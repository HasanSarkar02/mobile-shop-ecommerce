<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\SerialNumberStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\SerialNumber;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    actingAsTenant();
});

function serialLinkageVariant(int $serialCount, array $overrides = []): ProductVariant
{
    $variant = createTestVariant(['inventory_type' => 'serialized'] + $overrides);
    SerialNumber::factory()->count($serialCount)->for($variant, 'variant')->create(['status' => 'available']);

    return $variant;
}

function serialLinkageOrder(ProductVariant $variant, int $quantity, string $guestEmail = 'guest@example.com'): Order
{
    $cart = Cart::query()->create(['tenant_id' => tenant()->id, 'customer_id' => null, 'currency_code' => 'BDT']);
    $cart->items()->create([
        'tenant_id' => tenant()->id,
        'product_variant_id' => $variant->id,
        'quantity' => $quantity,
        'unit_price' => $variant->price,
    ]);

    return app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Test Guest',
        'guest_email' => $guestEmail,
        'guest_phone' => '01700000000',
    ]);
}

describe('ORDER SERIAL LINKAGE — exact serial to order item attribution', function (): void {
    it('links every sold serial to the exact order item at confirmation', function (): void {
        $variant = serialLinkageVariant(3);
        $order = serialLinkageOrder($variant, 2);
        $item = $order->items()->first();

        app(OrderService::class)->updateStatus($order, OrderStatus::Confirmed);

        $sold = SerialNumber::query()->where('product_variant_id', $variant->id)->where('status', 'sold')->get();
        expect($sold)->toHaveCount(2);
        expect($sold->pluck('order_item_id')->unique()->all())->toBe([$item->id]);
        expect($item->fresh()->serialNumbers->pluck('imei_or_serial')->sort()->values()->all())
            ->toBe($sold->pluck('imei_or_serial')->sort()->values()->all());
    });

    it('blocks a second order from claiming the same serial', function (): void {
        $variant = serialLinkageVariant(1);
        $orderA = serialLinkageOrder($variant, 1);
        app(OrderService::class)->updateStatus($orderA, OrderStatus::Confirmed);

        $serial = SerialNumber::query()->where('product_variant_id', $variant->id)->first();
        expect($serial->status)->toBe(SerialNumberStatus::Sold);
        expect($serial->order_item_id)->toBe($orderA->items()->first()->id);

        expect(fn () => serialLinkageOrder($variant, 1))->toThrow(InsufficientStockException::class);

        $fresh = SerialNumber::query()->where('product_variant_id', $variant->id)->first();
        expect($fresh->status)->toBe(SerialNumberStatus::Sold);
        expect($fresh->order_item_id)->toBe($orderA->items()->first()->id);
    });

    it('returns exactly the linked serials on cancellation and leaves other orders untouched', function (): void {
        $variant = serialLinkageVariant(4);
        $orderA = serialLinkageOrder($variant, 2, 'a@example.com');
        $orderB = serialLinkageOrder($variant, 1, 'b@example.com');
        $services = app(OrderService::class);

        $services->updateStatus($orderA, OrderStatus::Confirmed);
        $services->updateStatus($orderB, OrderStatus::Confirmed);

        $itemA = $orderA->items()->first();
        $itemB = $orderB->items()->first();
        $linkedToA = SerialNumber::query()->where('status', 'sold')->where('order_item_id', $itemA->id)->get();
        expect($linkedToA)->toHaveCount(2);

        $services->cancelOrder($orderA, 'Return to stock');

        expect(SerialNumber::query()->where('status', 'sold')->where('order_item_id', $itemA->id)->count())->toBe(0);
        foreach ($linkedToA as $serial) {
            expect($serial->fresh()->status)->toBe(SerialNumberStatus::Available);
            expect($serial->fresh()->order_item_id)->toBeNull();
        }

        $stillSold = SerialNumber::query()->where('product_variant_id', $variant->id)->where('status', 'sold')->get();
        expect($stillSold)->toHaveCount(1);
        expect($stillSold->first()->order_item_id)->toBe($itemB->id);
    });

    it('never returns a serial that is not linked to the cancelled order item', function (): void {
        $variant = serialLinkageVariant(2);
        $order = serialLinkageOrder($variant, 1);
        $services = app(OrderService::class);

        $services->updateStatus($order, OrderStatus::Confirmed);

        SerialNumber::query()->where('product_variant_id', $variant->id)->where('status', 'sold')->first()
            ->update(['order_item_id' => null]);

        expect(fn () => $services->cancelOrder($order, 'Tampered link'))
            ->toThrow(InsufficientStockException::class);

        expect($order->fresh()->status)->toBe(OrderStatus::Confirmed);
        expect(SerialNumber::query()->where('product_variant_id', $variant->id)->where('status', 'sold')->count())->toBe(1);
    });

    it('leaves non-serialized orders completely unchanged', function (): void {
        [$cart, $variant] = createCartWithVariant(2);
        $order = app(OrderService::class)->createFromCart($cart, [
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '01700000000',
        ]);
        $services = app(OrderService::class);

        $services->updateStatus($order, OrderStatus::Confirmed);
        $services->cancelOrder($order, 'Return to stock');

        $item = StockItem::query()->where('product_variant_id', $variant->id)->first();
        expect($item->quantity)->toBe(10);
        expect(SerialNumber::query()->where('product_variant_id', $variant->id)->count())->toBe(0);
    });

    it('rolls back confirmation when serial linkage fails', function (): void {
        $variant = serialLinkageVariant(2);
        $order = serialLinkageOrder($variant, 2);

        SerialNumber::query()->where('product_variant_id', $variant->id)->where('status', 'available')->first()
            ->update(['status' => 'defective']);

        expect(fn () => app(OrderService::class)->updateStatus($order, OrderStatus::Confirmed))
            ->toThrow(InsufficientStockException::class);

        expect($order->fresh()->status)->toBe(OrderStatus::Pending);
        expect(SerialNumber::query()->where('product_variant_id', $variant->id)->where('status', 'sold')->count())->toBe(0);
        expect(StockMovement::query()->where('product_variant_id', $variant->id)->where('type', 'sale')->count())->toBe(0);
    });

    it('requires an order item to return serialized stock', function (): void {
        $variant = serialLinkageVariant(1);
        $order = serialLinkageOrder($variant, 1);
        app(OrderService::class)->updateStatus($order, OrderStatus::Confirmed);

        expect(fn () => app(InventoryService::class)->restockFromCancellation($variant, 1))
            ->toThrow(InvalidArgumentException::class);
    });

    it('records the exact returned serials in the cancellation audit trail', function (): void {
        $variant = serialLinkageVariant(3);
        $order = serialLinkageOrder($variant, 2);
        $services = app(OrderService::class);

        $services->updateStatus($order, OrderStatus::Confirmed);
        $item = $order->items()->first();
        $linked = SerialNumber::query()->where('status', 'sold')->where('order_item_id', $item->id)->orderBy('id')->pluck('imei_or_serial')->all();

        $services->cancelOrder($order, 'Return to stock');

        $event = $order->fresh()->events()->where('type', 'status_changed')->latest('id')->first();
        expect($event->metadata['returned_serials'][$item->variant_sku_snapshot])->toBe($linked);
    });
});
