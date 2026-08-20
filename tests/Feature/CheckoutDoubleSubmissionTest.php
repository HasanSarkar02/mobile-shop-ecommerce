<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Exceptions\CartAlreadyConvertedException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Services\OrderService;
use App\Support\Tenancy\Tenancy;

function makeCartForCheckout(Tenant $tenant): Cart
{
    // Product has no tenant() relation method (verified against app/Models/Product.php),
    // so tenant_id must be set explicitly rather than via factory ->for($tenant).
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    // Dropship variants skip stock reservation entirely (InventoryService::
    // reserve() returns immediately for non-'stock' fulfillment strategies),
    // which keeps this test focused on cart-conversion locking rather than
    // needing a full Location/StockItem setup unrelated to what it verifies.
    // tenant_id and product_id are set explicitly for the same reason as above.
    $variant = ProductVariant::factory()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'fulfillment_strategy' => 'dropship',
        'price' => 1000, // Explicitly set price to avoid null math errors
    ]);

    $cart = Cart::query()->create(['tenant_id' => $tenant->id, 'currency_code' => 'BDT']);

    CartItem::query()->create([
        'tenant_id' => $tenant->id,
        'cart_id' => $cart->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'unit_price' => $variant->price ?? 1000,
    ]);

    return $cart;
}

it('throws CartAlreadyConvertedException on a second createFromCart call for the same cart', function (): void {
    \Illuminate\Support\Facades\Event::fake();
    $tenant = Tenant::factory()->create();
    app(Tenancy::class)->set($tenant);

    // 1. Create a Payment Method (OrderService requires this to process checkout)
    // 1. Create a Payment Method using query()->create() instead of a factory
    $paymentMethod = PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Cash on Delivery', // Added a standard name
        'type' => PaymentMethodType::Cod, 
        'is_active' => true,
    ]);

    $cart = makeCartForCheckout($tenant);
    
    $orderData = [
        'guest_name' => 'Test Guest',
        'guest_email' => 'guest@example.com',
        'guest_phone' => '01700000000',
        'payment_method_id' => $paymentMethod->id, // 2. Pass it here!
    ];

    $orders = app(OrderService::class);
    $firstOrder = $orders->createFromCart($cart, $orderData);

    expect(Order::query()->where('tenant_id', $tenant->id)->count())->toBe(1);

    // Second submission for the exact same (now-converted) cart — this is
    // what a double-click or client retry produces.
    expect(fn () => $orders->createFromCart($cart->fresh(), $orderData))
        ->toThrow(CartAlreadyConvertedException::class);

    // No second order was created.
    expect(Order::query()->where('tenant_id', $tenant->id)->count())->toBe(1);
    
    // Safely verify the order matches the first one without triggering "id on null"
    $savedOrder = Order::query()->where('tenant_id', $tenant->id)->first();
    expect($savedOrder)->not->toBeNull();
    expect($savedOrder->id)->toBe($firstOrder->id);

    app(Tenancy::class)->set(null);
});
