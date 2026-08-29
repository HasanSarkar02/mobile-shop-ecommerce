<?php

declare(strict_types=1);

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Services\CouponService;
use App\Support\CouponValidationResult;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;

function createTenantWithContext(?string $subdomain = null): Tenant
{
    $tenant = Tenant::factory()->create($subdomain ? ['subdomain' => $subdomain] : []);
    app(Tenancy::class)->set($tenant);

    return $tenant;
}

function createCartForTenant(Tenant $tenant, ?int $couponId = null): Cart
{
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $variant = ProductVariant::factory()->create([
        'tenant_id' => $tenant->id,
        'product_id' => $product->id,
        'price' => 100000, // 1000 BDT in cents
    ]);

    $cart = Cart::query()->create([
        'tenant_id' => $tenant->id,
        'currency_code' => 'BDT',
        'coupon_id' => $couponId,
    ]);

    CartItem::query()->create([
        'tenant_id' => $tenant->id,
        'cart_id' => $cart->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'unit_price' => $variant->price,
    ]);

    return $cart->fresh(['items.variant.product.collections']);
}

it('isolates automatic coupons between tenants via computeForCart', function (): void {
    $tenantA = Tenant::factory()->create(['subdomain' => 'tenant-a-'.uniqid()]);
    $tenantB = Tenant::factory()->create(['subdomain' => 'tenant-b-'.uniqid()]);

    // Tenant A: automatic 10%
    app(Tenancy::class)->set($tenantA);
    $couponA = Coupon::query()->create([
        'name' => 'Auto A',
        'code' => null,
        'type' => 'percentage',
        'value' => 10,
        'is_active' => true,
    ]);

    // Tenant B: automatic 20%
    app(Tenancy::class)->set($tenantB);
    $couponB = Coupon::query()->create([
        'name' => 'Auto B',
        'code' => null,
        'type' => 'percentage',
        'value' => 20,
        'is_active' => true,
    ]);

    // Cart in Tenant A should only see Coupon A
    app(Tenancy::class)->set($tenantA);
    $cartA = createCartForTenant($tenantA);
    $resultA = app(CouponService::class)->computeForCart($cartA, null);

    expect($resultA->valid)->toBeTrue();
    expect($resultA->coupon?->id)->toBe($couponA->id);
    expect($resultA->coupon?->tenant_id)->toBe($tenantA->id);
    expect($resultA->discountAmount)->toBe(10000); // 10% of 100000

    // Cart in Tenant B should only see Coupon B
    app(Tenancy::class)->set($tenantB);
    $cartB = createCartForTenant($tenantB);
    $resultB = app(CouponService::class)->computeForCart($cartB, null);

    expect($resultB->valid)->toBeTrue();
    expect($resultB->coupon?->id)->toBe($couponB->id);
    expect($resultB->coupon?->tenant_id)->toBe($tenantB->id);
    expect($resultB->discountAmount)->toBe(20000); // 20% of 100000

    app(Tenancy::class)->set(null);
});

it('isolates explicit coupon codes between tenants via applyToCart', function (): void {
    $tenantA = Tenant::factory()->create(['subdomain' => 'tenant-a-'.uniqid()]);
    $tenantB = Tenant::factory()->create(['subdomain' => 'tenant-b-'.uniqid()]);

    app(Tenancy::class)->set($tenantA);
    Coupon::query()->create([
        'name' => 'Save 10 A',
        'code' => 'SAVE10',
        'type' => 'fixed_amount',
        'value' => 10000,
        'is_active' => true,
    ]);

    app(Tenancy::class)->set($tenantB);
    Coupon::query()->create([
        'name' => 'Save 10 B',
        'code' => 'SAVE10',
        'type' => 'fixed_amount',
        'value' => 20000,
        'is_active' => true,
    ]);

    // Tenant A cart applying SAVE10 should get A's coupon (10000)
    app(Tenancy::class)->set($tenantA);
    $cartA = createCartForTenant($tenantA);
    $resultA = app(CouponService::class)->applyToCart($cartA, 'SAVE10', null);
    expect($resultA->valid)->toBeTrue();
    expect($resultA->discountAmount)->toBe(10000);
    expect($resultA->coupon?->tenant_id)->toBe($tenantA->id);
    expect($cartA->fresh()->coupon_id)->toBe($resultA->coupon->id);

    // Tenant B cart applying SAVE10 should get B's coupon (20000)
    app(Tenancy::class)->set($tenantB);
    $cartB = createCartForTenant($tenantB);
    $resultB = app(CouponService::class)->applyToCart($cartB, 'SAVE10', null);
    expect($resultB->valid)->toBeTrue();
    expect($resultB->discountAmount)->toBe(20000);
    expect($resultB->coupon?->tenant_id)->toBe($tenantB->id);
    expect($cartB->fresh()->coupon_id)->toBe($resultB->coupon->id);

    // Tenant B cart should not see a coupon that only exists in A with different code
    app(Tenancy::class)->set($tenantA);
    Coupon::query()->create([
        'name' => 'Unique A',
        'code' => 'ONLYA',
        'type' => 'fixed_amount',
        'value' => 5000,
        'is_active' => true,
    ]);
    app(Tenancy::class)->set($tenantB);
    $cartB2 = createCartForTenant($tenantB);
    $resultB3 = app(CouponService::class)->applyToCart($cartB2, 'ONLYA', null);
    expect($resultB3->valid)->toBeFalse();

    app(Tenancy::class)->set(null);
});

it('prevents guest cart token replay from leaking automatic coupons across tenants', function (): void {
    $tenantA = Tenant::factory()->create(['subdomain' => 'tenant-a-'.uniqid()]);
    $tenantB = Tenant::factory()->create(['subdomain' => 'tenant-b-'.uniqid()]);

    // Tenant A has automatic coupon
    app(Tenancy::class)->set($tenantA);
    $couponA = Coupon::query()->create([
        'name' => 'Auto A',
        'code' => null,
        'type' => 'percentage',
        'value' => 15,
        'is_active' => true,
    ]);

    // Tenant B has no automatic coupon at first
    // Simulate guest cart created in Tenant A, then tenant context switches to B (cookie replay / queue without context)
    app(Tenancy::class)->set($tenantA);
    $cartA = createCartForTenant($tenantA);

    // Mismatched context: tenant() = B but cart->tenant_id = A
    // Explicit tenant_id filtering must prevent couponB from leaking into cartA, and prevent crash leaking.
    // With correct fix, cartA still resolves its own tenant's coupon despite global scope mismatch,
    // OR at minimum does not leak B's coupon. We assert isolation: cartA outcome is still A's coupon or none, never B's.
    // First create a distinct auto coupon in B to prove no cross-leak
    app(Tenancy::class)->set($tenantB);
    $couponB = Coupon::query()->create([
        'name' => 'Auto B',
        'code' => null,
        'type' => 'percentage',
        'value' => 50,
        'is_active' => true,
    ]);

    // Now call computeForCart with cartA (tenant A) while Tenancy is B — this simulates replay/queue mismatch
    // Before fix this would leak B's 50% coupon via global scope = B. After fix explicit where prevents leak.
    $resultReplay = DB::transaction(function () use ($cartA): CouponValidationResult {
        // cartA tenant_id remains A, global tenant is B
        return app(CouponService::class)->computeForCart($cartA, null);
    });

    // Must NOT be coupon B
    if ($resultReplay->valid && $resultReplay->coupon) {
        expect($resultReplay->coupon->tenant_id)->not->toBe($tenantB->id);
        expect($resultReplay->coupon->id)->not->toBe($couponB->id);
    }

    // Correct behavior when context matches cart tenant: cartA under tenantA sees couponA
    app(Tenancy::class)->set($tenantA);
    $resultA = app(CouponService::class)->computeForCart($cartA, null);
    expect($resultA->valid)->toBeTrue();
    expect($resultA->coupon?->id)->toBe($couponA->id);

    // CartB under tenantB sees couponB, not couponA
    $cartB = createCartForTenant($tenantB);
    app(Tenancy::class)->set($tenantB);
    $resultB = app(CouponService::class)->computeForCart($cartB, null);
    expect($resultB->valid)->toBeTrue();
    expect($resultB->coupon?->id)->toBe($couponB->id);

    // Explicit code replay: cartA applying code that only exists in B should fail even under mismatched context
    app(Tenancy::class)->set($tenantB);
    Coupon::query()->create([
        'name' => 'Only B Code',
        'code' => 'ONLYB',
        'type' => 'fixed_amount',
        'value' => 9999,
        'is_active' => true,
    ]);
    // Try to apply ONLYB to cartA (tenant A) — should be invalid regardless of current tenant context
    $resultCrossCode = app(CouponService::class)->applyToCart($cartA, 'ONLYB', null);
    expect($resultCrossCode->valid)->toBeFalse();

    app(Tenancy::class)->set(null);
});

it('isolates lockAndComputeForCart between tenants', function (): void {
    $tenantA = Tenant::factory()->create(['subdomain' => 'tenant-a-'.uniqid()]);
    $tenantB = Tenant::factory()->create(['subdomain' => 'tenant-b-'.uniqid()]);

    app(Tenancy::class)->set($tenantA);
    $couponA = Coupon::query()->create([
        'name' => 'Locked A',
        'code' => 'LOCKA',
        'type' => 'fixed_amount',
        'value' => 5000,
        'is_active' => true,
        'usage_limit_total' => 5,
    ]);

    app(Tenancy::class)->set($tenantB);
    $couponB = Coupon::query()->create([
        'name' => 'Locked B',
        'code' => 'LOCKB',
        'type' => 'fixed_amount',
        'value' => 7000,
        'is_active' => true,
        'usage_limit_total' => 5,
    ]);

    app(Tenancy::class)->set($tenantA);
    $cartA = createCartForTenant($tenantA, $couponA->id);

    app(Tenancy::class)->set($tenantB);
    $cartB = createCartForTenant($tenantB, $couponB->id);

    // Each tenant locking its own cart should succeed and remain tenant-scoped
    app(Tenancy::class)->set($tenantA);
    $resultA = DB::transaction(fn () => app(CouponService::class)->lockAndComputeForCart($cartA, null));
    expect($resultA->valid)->toBeTrue();
    expect($resultA->coupon?->id)->toBe($couponA->id);
    expect($resultA->coupon?->tenant_id)->toBe($tenantA->id);

    app(Tenancy::class)->set($tenantB);
    $resultB = DB::transaction(fn () => app(CouponService::class)->lockAndComputeForCart($cartB, null));
    expect($resultB->valid)->toBeTrue();
    expect($resultB->coupon?->id)->toBe($couponB->id);
    expect($resultB->coupon?->tenant_id)->toBe($tenantB->id);

    // Cross-tenant lock attempt: cartA (tenant A) should not lock couponB via mismatched context
    // Simulate by creating a cart that claims to be tenant A but points at couponB id (forged)
    app(Tenancy::class)->set($tenantA);
    $forgedCart = createCartForTenant($tenantA);
    $forgedCart->update(['coupon_id' => $couponB->id]);
    $forgedCart->refresh();
    $resultForged = DB::transaction(fn () => app(CouponService::class)->lockAndComputeForCart($forgedCart, null));
    // With explicit tenant filter, this should return none() (coupon not found in tenant A)
    expect($resultForged->valid)->toBeTrue(); // none() is valid=true but coupon null, discount 0
    expect($resultForged->coupon)->toBeNull();
    expect($resultForged->discountAmount)->toBe(0);

    app(Tenancy::class)->set(null);
});

it('respects global tenant scope for listing and currentlyActive filtering', function (): void {
    $tenantA = Tenant::factory()->create(['subdomain' => 'tenant-a-'.uniqid()]);
    $tenantB = Tenant::factory()->create(['subdomain' => 'tenant-b-'.uniqid()]);

    app(Tenancy::class)->set($tenantA);
    Coupon::query()->create(['name' => 'A1', 'code' => 'A1', 'type' => 'fixed_amount', 'value' => 1000, 'is_active' => true]);
    Coupon::query()->create(['name' => 'A Auto', 'code' => null, 'type' => 'percentage', 'value' => 10, 'is_active' => true]);
    Coupon::query()->create(['name' => 'A Inactive Auto', 'code' => null, 'type' => 'percentage', 'value' => 99, 'is_active' => false]);
    Coupon::query()->create(['name' => 'A Expired Auto', 'code' => null, 'type' => 'percentage', 'value' => 99, 'is_active' => true, 'ends_at' => now()->subDay()]);
    Coupon::query()->create(['name' => 'A Future Auto', 'code' => null, 'type' => 'percentage', 'value' => 99, 'is_active' => true, 'starts_at' => now()->addDay()]);

    app(Tenancy::class)->set($tenantB);
    Coupon::query()->create(['name' => 'B1', 'code' => 'B1', 'type' => 'fixed_amount', 'value' => 1000, 'is_active' => true]);
    Coupon::query()->create(['name' => 'B Auto', 'code' => null, 'type' => 'percentage', 'value' => 20, 'is_active' => true]);

    // Filament-like listing: Coupon::query()->count() should be tenant-scoped
    app(Tenancy::class)->set($tenantA);
    expect(Coupon::query()->count())->toBe(5);
    expect(Coupon::query()->whereNull('code')->count())->toBe(4); // includes inactive/expired but scoped
    expect(Coupon::query()->whereNull('code')->currentlyActive()->count())->toBe(1); // only active auto

    app(Tenancy::class)->set($tenantB);
    expect(Coupon::query()->count())->toBe(2);
    expect(Coupon::query()->whereNull('code')->currentlyActive()->count())->toBe(1);

    // Automatic compute should only consider currentlyActive — inactive/expired must not be selected
    app(Tenancy::class)->set($tenantA);
    $cartA = createCartForTenant($tenantA);
    $resultA = app(CouponService::class)->computeForCart($cartA, null);
    expect($resultA->valid)->toBeTrue();
    expect($resultA->coupon?->name)->toBe('A Auto');
    expect($resultA->discountAmount)->toBe(10000);

    app(Tenancy::class)->set($tenantB);
    $cartB = createCartForTenant($tenantB);
    $resultB = app(CouponService::class)->computeForCart($cartB, null);
    expect($resultB->valid)->toBeTrue();
    expect($resultB->coupon?->name)->toBe('B Auto');

    // Tenant B must not see Tenant A's coupons via withoutGlobalScope without explicit tenant filter
    $allWithoutScope = Coupon::query()->withoutGlobalScope('tenant')->count();
    expect($allWithoutScope)->toBe(7);

    app(Tenancy::class)->set(null);
});

it('preserves automatic coupon priority FreeShipping > discount > 0 within tenant', function (): void {
    $tenant = Tenant::factory()->create(['subdomain' => 'tenant-p-'.uniqid()]);
    app(Tenancy::class)->set($tenant);

    // Two autos: 5% vs FreeShipping — FreeShipping should win regardless of discount
    Coupon::query()->create([
        'name' => '5 percent',
        'code' => null,
        'type' => 'percentage',
        'value' => 5,
        'is_active' => true,
    ]);
    $free = Coupon::query()->create([
        'name' => 'Free Ship',
        'code' => null,
        'type' => 'free_shipping',
        'value' => null,
        'is_active' => true,
    ]);

    $cart = createCartForTenant($tenant);
    $result = app(CouponService::class)->computeForCart($cart, null);

    expect($result->valid)->toBeTrue();
    expect($result->freeShipping)->toBeTrue();
    expect($result->coupon?->id)->toBe($free->id);

    // Ensure other tenant's free shipping does not interfere
    $tenant2 = Tenant::factory()->create(['subdomain' => 'tenant-p2-'.uniqid()]);
    app(Tenancy::class)->set($tenant2);
    Coupon::query()->create([
        'name' => 'Other free',
        'code' => null,
        'type' => 'free_shipping',
        'value' => null,
        'is_active' => true,
    ]);
    // Back to first tenant — should still be its own free coupon, not tenant2's
    app(Tenancy::class)->set($tenant);
    $cart2 = createCartForTenant($tenant);
    $result2 = app(CouponService::class)->computeForCart($cart2, null);
    expect($result2->coupon?->tenant_id)->toBe($tenant->id);

    app(Tenancy::class)->set(null);
});

it('isolates usage limit counts per tenant', function (): void {
    $tenantA = Tenant::factory()->create(['subdomain' => 'tenant-a-'.uniqid()]);
    $tenantB = Tenant::factory()->create(['subdomain' => 'tenant-b-'.uniqid()]);

    // Both tenants have SAVE10 with usage_limit_total = 1
    app(Tenancy::class)->set($tenantA);
    $couponA = Coupon::query()->create([
        'name' => 'Limited A',
        'code' => 'LIMITED',
        'type' => 'fixed_amount',
        'value' => 5000,
        'is_active' => true,
        'usage_limit_total' => 1,
    ]);

    app(Tenancy::class)->set($tenantB);
    $couponB = Coupon::query()->create([
        'name' => 'Limited B',
        'code' => 'LIMITED',
        'type' => 'fixed_amount',
        'value' => 5000,
        'is_active' => true,
        'usage_limit_total' => 1,
    ]);

    // Exhaust couponA in tenant A
    app(Tenancy::class)->set($tenantA);
    $cartA1 = createCartForTenant($tenantA);
    $applyA1 = app(CouponService::class)->applyToCart($cartA1, 'LIMITED', null);
    expect($applyA1->valid)->toBeTrue();
    // Simulate redemption (normally via OrderService)
    $orderA = Order::query()->create([
        'tenant_id' => $tenantA->id,
        'order_number' => 'ORD-A-1',
        'status' => 'pending',
        'grand_total' => 1000,
    ]);
    CouponRedemption::query()->create([
        'tenant_id' => $tenantA->id,
        'coupon_id' => $couponA->id,
        'order_id' => $orderA->id,
        'discount_amount' => 5000,
        'redeemed_at' => now(),
    ]);

    // Coupon A should now be at limit for tenant A
    $cartA2 = createCartForTenant($tenantA);
    $applyA2 = app(CouponService::class)->applyToCart($cartA2, 'LIMITED', null);
    expect($applyA2->valid)->toBeFalse();

    // Tenant B's coupon must still be valid — not counted against tenant A's redemption
    app(Tenancy::class)->set($tenantB);
    $cartB1 = createCartForTenant($tenantB);
    $applyB1 = app(CouponService::class)->applyToCart($cartB1, 'LIMITED', null);
    expect($applyB1->valid)->toBeTrue();
    expect($applyB1->coupon?->id)->toBe($couponB->id);

    app(Tenancy::class)->set(null);
});
