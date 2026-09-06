<?php

declare(strict_types=1);

use App\Models\BdDistrict;
use App\Models\BdDivision;
use App\Models\BdUpazila;
use App\Models\Cart;
use App\Models\TenantShippingRate;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    actingAsTenant();
});

function weightCreateDivision(string $name = 'Dhaka Div'): BdDivision
{
    return BdDivision::query()->create([
        'name_en' => $name.' '.uniqid(),
        'name_bn' => $name,
        'bbs_code' => 'bbs-'.uniqid(),
    ]);
}

function weightCreateDistrict(BdDivision $division, string $name = 'Dhaka Dist'): BdDistrict
{
    return BdDistrict::query()->create([
        'division_id' => $division->id,
        'name_en' => $name.' '.uniqid(),
        'name_bn' => $name,
        'bbs_code' => 'b-'.uniqid(),
    ]);
}

function weightCreateUpazila(BdDistrict $district, string $name = 'Savar'): BdUpazila
{
    return BdUpazila::query()->create([
        'district_id' => $district->id,
        'name_en' => $name.' '.uniqid(),
        'name_bn' => $name,
    ]);
}

it('snapshots variant weight_grams to order items and order total_weight_grams on createFromCart', function (): void {
    $variantA = createTestVariant(['price' => 15000, 'cost_price' => 10000, 'weight_grams' => 500]);
    $variantB = createTestVariant(['price' => 20000, 'cost_price' => 15000, 'weight_grams' => 800]);
    app(InventoryService::class)->restock($variantA, 10);
    app(InventoryService::class)->restock($variantB, 10);

    $cart = Cart::query()->create([
        'tenant_id' => tenant()->id,
        'customer_id' => null,
        'currency_code' => 'BDT',
    ]);
    $cart->items()->create([
        'tenant_id' => tenant()->id,
        'product_variant_id' => $variantA->id,
        'quantity' => 2,
        'unit_price' => $variantA->price,
    ]);
    $cart->items()->create([
        'tenant_id' => tenant()->id,
        'product_variant_id' => $variantB->id,
        'quantity' => 1,
        'unit_price' => $variantB->price,
    ]);

    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest-weight-1@example.com',
        'guest_phone' => '01700000001',
    ]);

    $freshOrder = $order->fresh();
    $itemA = $freshOrder->items()->where('product_variant_id', $variantA->id)->firstOrFail();
    $itemB = $freshOrder->items()->where('product_variant_id', $variantB->id)->firstOrFail();

    expect($itemA->unit_weight_grams)->toBe(500)
        ->and($itemA->line_weight_grams)->toBe(1000)
        ->and($itemB->unit_weight_grams)->toBe(800)
        ->and($itemB->line_weight_grams)->toBe(800)
        ->and($freshOrder->total_weight_grams)->toBe(1800);
});

it('stores null weight snapshots when variant weight_grams is null', function (): void {
    $variant = createTestVariant(['price' => 10000, 'cost_price' => 5000, 'weight_grams' => null]);
    app(InventoryService::class)->restock($variant, 5);

    $cart = Cart::query()->create([
        'tenant_id' => tenant()->id,
        'customer_id' => null,
        'currency_code' => 'BDT',
    ]);
    $cart->items()->create([
        'tenant_id' => tenant()->id,
        'product_variant_id' => $variant->id,
        'quantity' => 3,
        'unit_price' => $variant->price,
    ]);

    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest-weight-2@example.com',
        'guest_phone' => '01700000002',
    ]);

    $item = $order->fresh()->items()->firstOrFail();

    expect($item->unit_weight_grams)->toBeNull()
        ->and($item->line_weight_grams)->toBeNull()
        ->and($order->fresh()->total_weight_grams)->toBe(0);
});

it('keeps weight snapshot immutable after variant weight_grams changes', function (): void {
    $variant = createTestVariant(['price' => 15000, 'cost_price' => 10000, 'weight_grams' => 400]);
    app(InventoryService::class)->restock($variant, 10);

    $cart = Cart::query()->create([
        'tenant_id' => tenant()->id,
        'customer_id' => null,
        'currency_code' => 'BDT',
    ]);
    $cart->items()->create([
        'tenant_id' => tenant()->id,
        'product_variant_id' => $variant->id,
        'quantity' => 2,
        'unit_price' => $variant->price,
    ]);

    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest-weight-3@example.com',
        'guest_phone' => '01700000003',
    ]);

    $item = $order->fresh()->items()->firstOrFail();
    expect($item->unit_weight_grams)->toBe(400)
        ->and($item->line_weight_grams)->toBe(800);

    // Change variant weight after order
    $variant->update(['weight_grams' => 999]);

    $freshItem = $order->fresh()->items()->firstOrFail();
    expect($freshItem->unit_weight_grams)->toBe(400)
        ->and($freshItem->line_weight_grams)->toBe(800)
        ->and($order->fresh()->total_weight_grams)->toBe(800);
});

it('snapshots delivery_zone_snapshot from matched TenantShippingRate name', function (): void {
    $division = weightCreateDivision('Dhaka Div');
    $district = weightCreateDistrict($division, 'Dhaka Dist');
    $upazila = weightCreateUpazila($district, 'Savar');

    TenantShippingRate::query()->create([
        'name' => 'Inside Dhaka',
        'bd_division_id' => $division->id,
        'bd_district_id' => $district->id,
        'bd_upazila_id' => $upazila->id,
        'charge' => 6000,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $variant = createTestVariant(['price' => 10000, 'cost_price' => 5000, 'weight_grams' => 300]);
    app(InventoryService::class)->restock($variant, 5);

    $cart = Cart::query()->create([
        'tenant_id' => tenant()->id,
        'customer_id' => null,
        'currency_code' => 'BDT',
    ]);
    $cart->items()->create([
        'tenant_id' => tenant()->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'unit_price' => $variant->price,
    ]);

    $order = app(OrderService::class)->createFromCart($cart, [
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest-weight-4@example.com',
        'guest_phone' => '01700000004',
        'shipping_address' => [
            'recipient_name' => 'Test Recipient',
            'phone' => '01700000005',
            'address_line_1' => '123 Test St',
            'city' => 'Dhaka',
            'country' => 'Bangladesh',
            'bd_division_id' => $division->id,
            'bd_district_id' => $district->id,
            'bd_upazila_id' => $upazila->id,
        ],
    ]);

    $freshOrder = $order->fresh();

    expect($freshOrder->delivery_zone_snapshot)->toBe('Inside Dhaka')
        ->and($freshOrder->shipping_cost)->toBe(6000)
        ->and($freshOrder->items()->firstOrFail()->unit_weight_grams)->toBe(300);
});
