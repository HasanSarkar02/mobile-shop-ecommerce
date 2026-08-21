<?php

declare(strict_types=1);

use App\Enums\FulfillmentStrategy;
use App\Enums\InventoryType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Enums\VariantAvailability;
use App\Events\OrderCancelled;
use App\Events\OrderPaymentRecorded;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentMethod;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seedBootstrapPlans();
    Queue::fake();
    Event::fake([OrderPlaced::class, OrderStatusChanged::class, OrderCancelled::class, OrderPaymentRecorded::class]);
});

it('rejects preorder variant without ETA at domain boundary', function (): void {
    actingAsTenant();
    expect(function (): void {
        createTestVariant([
            'fulfillment_strategy' => FulfillmentStrategy::Preorder,
            'expected_available_at' => null,
            'price' => 1000,
        ]);
    })->toThrow(ValidationException::class);
});

it('rejects preorder variant with past ETA', function (): void {
    actingAsTenant();
    expect(function (): void {
        createTestVariant([
            'fulfillment_strategy' => FulfillmentStrategy::Preorder,
            'expected_available_at' => now()->subDay(),
            'price' => 1000,
        ]);
    })->toThrow(ValidationException::class);
});

it('allows stock variant without ETA', function (): void {
    actingAsTenant();
    $variant = createTestVariant([
        'fulfillment_strategy' => FulfillmentStrategy::Stock,
        'expected_available_at' => null,
        'price' => 1000,
    ]);

    expect($variant->exists)->toBeTrue();
});

it('creates one stock fulfillment for all-stock cart', function (): void {
    $tenant = actingAsTenant();
    $variant = createTestVariant([
        'fulfillment_strategy' => FulfillmentStrategy::Stock,
        'inventory_type' => InventoryType::Tracked,
        'availability' => VariantAvailability::InStock,
        'price' => 1000,
        'expected_available_at' => null,
    ]);
    app(InventoryService::class)->restock($variant, 10);

    $cart = Cart::query()->create(['tenant_id' => $tenant->id, 'session_token' => 'tok-stock', 'currency_code' => 'BDT']);
    CartItem::query()->create(['tenant_id' => $tenant->id, 'cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => $variant->price]);

    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Guest', 'guest_email' => 'guest-stock@example.com', 'guest_phone' => '01700000001',
        'shipping_address' => ['recipient_name' => 'Guest', 'phone' => '01700000001', 'address_line_1' => 'Addr', 'city' => 'Dhaka'],
    ]);
    $order->load('fulfillments', 'items');

    expect($order->fulfillments)->toHaveCount(1);
    expect($order->fulfillments->first()->fulfillment_group)->toBe('stock');
    expect($order->items->first()->fulfillment_strategy)->toBe('stock');
    expect($order->items->first()->order_fulfillment_id)->toBe($order->fulfillments->first()->id);
});

it('creates split fulfillments for mixed cart and snapshots ETA', function (): void {
    $tenant = actingAsTenant();
    $stock = createTestVariant([
        'fulfillment_strategy' => FulfillmentStrategy::Stock,
        'inventory_type' => InventoryType::Tracked,
        'availability' => VariantAvailability::InStock,
        'price' => 1000,
        'expected_available_at' => null,
    ]);
    app(InventoryService::class)->restock($stock, 10);

    $eta = now()->addDays(14)->startOfDay();
    $pre = createTestVariant([
        'fulfillment_strategy' => FulfillmentStrategy::Preorder,
        'expected_available_at' => $eta,
        'price' => 2000,
    ]);

    $cart = Cart::query()->create(['tenant_id' => $tenant->id, 'session_token' => 'tok-mixed', 'currency_code' => 'BDT']);
    CartItem::query()->create(['tenant_id' => $tenant->id, 'cart_id' => $cart->id, 'product_variant_id' => $stock->id, 'quantity' => 1, 'unit_price' => $stock->price]);
    CartItem::query()->create(['tenant_id' => $tenant->id, 'cart_id' => $cart->id, 'product_variant_id' => $pre->id, 'quantity' => 1, 'unit_price' => $pre->price]);

    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Guest', 'guest_email' => 'guest-mixed@example.com', 'guest_phone' => '01700000002',
        'shipping_address' => ['recipient_name' => 'Guest', 'phone' => '01700000002', 'address_line_1' => 'Addr', 'city' => 'Dhaka'],
        'preorder_ack_at' => now(),
    ]);
    $order->load('fulfillments', 'items');

    expect($order->fulfillments)->toHaveCount(2);
    $stockFul = $order->fulfillments->firstWhere('fulfillment_group', 'stock');
    $preFul = $order->fulfillments->firstWhere('fulfillment_group', 'preorder');
    expect($stockFul)->not->toBeNull();
    expect($preFul)->not->toBeNull();
    expect($preFul->expected_available_at->equalTo($eta))->toBeTrue();

    $stockItem = $order->items->firstWhere('product_variant_id', $stock->id);
    $preItem = $order->items->firstWhere('product_variant_id', $pre->id);
    expect($stockItem->fulfillment_strategy)->toBe('stock');
    expect($preItem->fulfillment_strategy)->toBe('preorder');
    expect($preItem->expected_available_at->equalTo($eta))->toBeTrue();
    expect($stockItem->order_fulfillment_id)->toBe($stockFul->id);
    expect($preItem->order_fulfillment_id)->toBe($preFul->id);
});

it('creates single preorder fulfillment for all-preorder cart', function (): void {
    $tenant = actingAsTenant();
    $eta = now()->addDays(21);
    $pre = createTestVariant([
        'fulfillment_strategy' => FulfillmentStrategy::Preorder,
        'expected_available_at' => $eta,
        'price' => 1500,
    ]);

    $cart = Cart::query()->create(['tenant_id' => $tenant->id, 'session_token' => 'tok-pre', 'currency_code' => 'BDT']);
    CartItem::query()->create(['tenant_id' => $tenant->id, 'cart_id' => $cart->id, 'product_variant_id' => $pre->id, 'quantity' => 2, 'unit_price' => $pre->price]);

    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Guest', 'guest_email' => 'guest-pre@example.com', 'guest_phone' => '01700000003',
        'shipping_address' => ['recipient_name' => 'Guest', 'phone' => '01700000003', 'address_line_1' => 'Addr', 'city' => 'Dhaka'],
        'preorder_ack_at' => now(),
    ]);
    $order->load('fulfillments');

    expect($order->fulfillments)->toHaveCount(1);
    expect($order->fulfillments->first()->fulfillment_group)->toBe('preorder');
});

it('persists preorder acknowledgement timestamp', function (): void {
    $tenant = actingAsTenant();
    $pre = createTestVariant([
        'fulfillment_strategy' => FulfillmentStrategy::Preorder,
        'expected_available_at' => now()->addDays(10),
        'price' => 1000,
    ]);
    $cart = Cart::query()->create(['tenant_id' => $tenant->id, 'session_token' => 'tok-ack', 'currency_code' => 'BDT']);
    CartItem::query()->create(['tenant_id' => $tenant->id, 'cart_id' => $cart->id, 'product_variant_id' => $pre->id, 'quantity' => 1, 'unit_price' => $pre->price]);

    $ackAt = now();
    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Guest', 'guest_email' => 'guest-ack@example.com', 'guest_phone' => '01700000004',
        'shipping_address' => ['recipient_name' => 'Guest', 'phone' => '01700000004', 'address_line_1' => 'Addr', 'city' => 'Dhaka'],
        'preorder_ack_at' => $ackAt,
    ]);
    $order->refresh();

    expect($order->preorder_ack_at)->not->toBeNull();
});

it('keeps historical null snapshot valid', function (): void {
    $tenant = actingAsTenant();
    $order = Order::factory()->create(['tenant_id' => $tenant->id, 'grand_total' => 1000]);
    $item = OrderItem::query()->create([
        'tenant_id' => $order->tenant_id,
        'order_id' => $order->id,
        'product_name_snapshot' => 'Old Item',
        'variant_sku_snapshot' => 'OLD-1',
        'unit_price' => 1000, 'quantity' => 1, 'line_total' => 1000,
        'fulfillment_strategy' => null,
        'expected_available_at' => null,
    ]);

    expect($item->fulfillment_strategy)->toBeNull();
});

it('allows guest to place preorder order via service', function (): void {
    $tenant = actingAsTenant();
    $pre = createTestVariant([
        'fulfillment_strategy' => FulfillmentStrategy::Preorder,
        'expected_available_at' => now()->addDays(7),
        'price' => 1200,
    ]);
    $cart = Cart::query()->create(['tenant_id' => $tenant->id, 'session_token' => 'tok-guest-pre', 'currency_code' => 'BDT']);
    CartItem::query()->create(['tenant_id' => $tenant->id, 'cart_id' => $cart->id, 'product_variant_id' => $pre->id, 'quantity' => 1, 'unit_price' => $pre->price]);

    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Guest Pre', 'guest_email' => 'guestpre2@example.com', 'guest_phone' => '01700000005',
        'shipping_address' => ['recipient_name' => 'Guest Pre', 'phone' => '01700000005', 'address_line_1' => 'Addr', 'city' => 'Dhaka'],
        'preorder_ack_at' => now(),
    ]);

    expect($order->status)->toBe(OrderStatus::Pending);
});

it('preorder payment follows full-upfront model', function (): void {
    $tenant = actingAsTenant();
    $pre = createTestVariant([
        'fulfillment_strategy' => FulfillmentStrategy::Preorder,
        'expected_available_at' => now()->addDays(7),
        'price' => 5000,
    ]);
    $cart = Cart::query()->create(['tenant_id' => $tenant->id, 'session_token' => 'tok-pay-pre', 'currency_code' => 'BDT']);
    CartItem::query()->create(['tenant_id' => $tenant->id, 'cart_id' => $cart->id, 'product_variant_id' => $pre->id, 'quantity' => 1, 'unit_price' => $pre->price]);

    $method = PaymentMethod::create([
        'tenant_id' => $tenant->id, 'name' => 'Manual bKash', 'type' => PaymentMethodType::ManualMfs,
        'provider' => 'bkash', 'account_number' => '01700000000', 'requires_verification' => true, 'is_active' => true, 'sort_order' => 0,
    ]);

    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Guest', 'guest_email' => 'guest-pay@example.com', 'guest_phone' => '01700000006',
        'shipping_address' => ['recipient_name' => 'Guest', 'phone' => '01700000006', 'address_line_1' => 'Addr', 'city' => 'Dhaka'],
        'payment_method_id' => $method->id,
        'preorder_ack_at' => now(),
    ]);

    // Full amount due upfront, not deposit
    expect($order->grand_total)->toBe(5000);
    $payment = app(OrderService::class)->recordPayment($order, $method, 5000, OrderPaymentStatus::Paid, 'TRX123');
    expect($payment->amount)->toBe(5000);
    expect(app(OrderService::class)->amountPaid($order->fresh()))->toBe(5000);
});
