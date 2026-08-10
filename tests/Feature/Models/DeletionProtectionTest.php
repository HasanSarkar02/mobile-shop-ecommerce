<?php

declare(strict_types=1);

use App\Exceptions\RecordDeletionNotAllowedException;
use App\Models\CouponRedemption;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;

it('blocks deleting an Order', function (): void {
    $tenant = Tenant::factory()->create();
    app(Tenancy::class)->set($tenant);

    $order = Order::query()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'ORD-2026-900001',
        'status' => 'pending',
        'grand_total' => 1000,
    ]);

    expect(fn () => $order->delete())->toThrow(RecordDeletionNotAllowedException::class);
    expect(Order::query()->whereKey($order->id)->exists())->toBeTrue();

    app(Tenancy::class)->set(null);
});

it('blocks deleting an OrderPayment', function (): void {
    $tenant = Tenant::factory()->create();
    app(Tenancy::class)->set($tenant);

    $order = Order::query()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'ORD-2026-900002',
        'status' => 'pending',
        'grand_total' => 1000,
    ]);

    $payment = OrderPayment::query()->create([
        'tenant_id' => $tenant->id,
        'order_id' => $order->id,
        'amount' => 1000,
        'status' => 'paid',
        'transaction_reference' => 'ref-900002',
    ]);

    expect(fn () => $payment->delete())->toThrow(RecordDeletionNotAllowedException::class);
    expect(OrderPayment::query()->whereKey($payment->id)->exists())->toBeTrue();

    app(Tenancy::class)->set(null);
});

it('blocks deleting a StockMovement', function (): void {
    $tenant = Tenant::factory()->create();
    app(Tenancy::class)->set($tenant);

    // TenantObserver::created() creates a default Location automatically
    // when the Tenant is created (verified in app/Observers/TenantObserver.php).
    $location = Location::query()->where('tenant_id', $tenant->id)->firstOrFail();

    $product = \App\Models\Product::factory()->create(['tenant_id' => $tenant->id]);
    $variant = ProductVariant::factory()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
    ]);

    $movement = StockMovement::query()->create([
        'tenant_id' => $tenant->id,
        'product_variant_id' => $variant->id,
        'location_id' => $location->id,
        'type' => 'restock',
        'quantity_change' => 10,
        'quantity_after' => 10,
    ]);

    // Change this line:
    expect(fn () => $movement->delete())->toThrow(LogicException::class);
    expect(StockMovement::query()->whereKey($movement->id)->exists())->toBeTrue();

    app(Tenancy::class)->set(null);
});

it('does not block deleting a CouponRedemption, since releaseForOrder() legitimately deletes them', function (): void {
    $tenant = Tenant::factory()->create();
    app(Tenancy::class)->set($tenant);

    $order = Order::query()->create([
        'tenant_id' => $tenant->id,
        'order_number' => 'ORD-2026-900003',
        'status' => 'pending',
        'grand_total' => 1000,
    ]);

    $coupon = \App\Models\Coupon::query()->create([
        'code' => 'DELOK',
        'name' => 'Deletable redemption coupon',
        'type' => 'fixed_amount',
        'value' => 500,
        'is_active' => true,
    ]);

    $redemption = CouponRedemption::query()->create([
        'coupon_id' => $coupon->id,
        'order_id' => $order->id,
        'discount_amount' => 500,
        'redeemed_at' => now(),
    ]);

    $redemption->delete();

    expect(CouponRedemption::query()->whereKey($redemption->id)->exists())->toBeFalse();

    app(Tenancy::class)->set(null);
});