<?php

declare(strict_types=1);

use App\Events\OrderPlaced;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Services\CartService;
use App\Services\InventoryService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    actingAsTenant();
});

function decimalProduct(float $sellByUnit = 0.5, string $code = 'kg'): Product
{
    $uom = UnitOfMeasure::query()->firstOrCreate(
        ['code' => $code],
        ['name' => $code, 'type' => 'weight', 'base_conversion_factor' => '1.000000'],
    );

    return Product::factory()->create([
        'status' => 'published',
        'uom_id' => $uom->id,
        'sell_by_unit' => number_format($sellByUnit, 3, '.', ''),
    ]);
}

it('adds decimal quantity to cart and calculates subtotal via bcmath', function (): void {
    $product = decimalProduct(0.5);
    $variant = createTestVariant(['price' => 10000]);
    $variant->update(['product_id' => $product->id]);
    $variant->refresh();
    app(InventoryService::class)->restock($variant, '10.000');

    $cart = app(CartService::class)->getOrCreateCart(null, 'tok-decimal-1');
    $item = app(CartService::class)->addItem($cart, $variant, '1.500');

    expect($item->quantity)->toBe('1.500')
        ->and($item->lineTotal())->toBe(15000)
        ->and(bcmul((string) $item->quantity, (string) $item->unit_price, 2))->toBe('15000.00');

    // Add another 0.500 → total 2.000 via bcadd
    $item2 = app(CartService::class)->addItem($cart, $variant, '0.500');
    expect($item2->fresh()->quantity)->toBe('2.000')
        ->and($item2->fresh()->lineTotal())->toBe(20000);
});

it('prevents adding invalid increment not multiple of sell_by_unit', function (): void {
    $product = decimalProduct(0.5);
    $variant = createTestVariant(['price' => 5000]);
    $variant->update(['product_id' => $product->id]);
    $variant->refresh();
    app(InventoryService::class)->restock($variant, '10.000');

    $cart = app(CartService::class)->getOrCreateCart(null, 'tok-decimal-2');

    expect(fn () => app(CartService::class)->addItem($cart, $variant, '0.333'))
        ->toThrow(ValidationException::class);
});

it('prevents updating cart quantity to invalid multiple', function (): void {
    $product = decimalProduct(1.0, 'pcs');
    $variant = createTestVariant(['price' => 2000]);
    $variant->update(['product_id' => $product->id]);
    $variant->refresh();
    app(InventoryService::class)->restock($variant, '10.000');

    $cart = app(CartService::class)->getOrCreateCart(null, 'tok-decimal-3');
    $item = app(CartService::class)->addItem($cart, $variant, '2.000');

    expect(fn () => app(CartService::class)->updateQuantity($item, '2.500'))
        ->toThrow(ValidationException::class);
});

it('maps decimal cart quantities to order line items perfectly', function (): void {
    Event::fake([OrderPlaced::class]);

    $product = decimalProduct(0.5);
    $variant = createTestVariant(['price' => 10000]);
    $variant->update(['product_id' => $product->id]);
    $variant->refresh();
    app(InventoryService::class)->restock($variant, '10.000');

    $cart = app(CartService::class)->getOrCreateCart(null, 'tok-decimal-4');
    app(CartService::class)->addItem($cart, $variant, '1.500');

    $order = app(OrderService::class)->createFromCart($cart, [
        'shipping_cost' => 0,
        'tax_total' => 0,
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest@example.com',
        'guest_phone' => '01234567890',
        'shipping_address' => [
            'recipient_name' => 'Test Guest',
            'phone' => '01234567890',
            'address_line_1' => '123 Test St',
            'city' => 'Dhaka',
        ],
    ]);

    $orderItem = $order->items()->firstOrFail();
    expect($orderItem->quantity)->toBe('1.500')
        ->and($orderItem->line_total)->toBe(15000)
        ->and($order->subtotal)->toBe(15000)
        ->and($order->grand_total)->toBe(15000);
});

it('exposes sell_by_unit as stepper step in product views', function (): void {
    $product = decimalProduct(0.5);
    $variant = createTestVariant(['price' => 10000]);
    $variant->update(['product_id' => $product->id]);
    $variant->refresh();

    // The Blade templates must render step="{{ $product->sell_by_unit ?? 1 }}"
    // so the storefront guides customers to valid increments.
    $pdp = file_get_contents(resource_path('views/storefront/products/show.blade.php'));
    $cart = file_get_contents(resource_path('views/livewire/cart-page.blade.php'));

    expect($pdp)->toContain('sell_by_unit')
        ->and($pdp)->toContain('step=')
        ->and($cart)->toContain('sell_by_unit')
        ->and($cart)->toContain('step=');
});

it('keeps discrete goods on integer path unchanged', function (): void {
    $product = Product::factory()->create(['status' => 'published', 'sell_by_unit' => null]);
    $variant = createTestVariant(['price' => 5000]);
    $variant->update(['product_id' => $product->id]);
    $variant->refresh();
    app(InventoryService::class)->restock($variant, '10.000');

    $cart = app(CartService::class)->getOrCreateCart(null, 'tok-discrete');
    $item = app(CartService::class)->addItem($cart, $variant, 2);
    expect($item->quantity)->toBe('2.000')
        ->and($item->lineTotal())->toBe(10000);

    // Integer still works
    $item2 = app(CartService::class)->addItem($cart, $variant, 1);
    expect($item2->fresh()->quantity)->toBe('3.000');
});
