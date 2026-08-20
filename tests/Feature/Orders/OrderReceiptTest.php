<?php

declare(strict_types=1);

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Enums\SubscriptionStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\ProductVariant;
use App\Models\SerialNumber;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
});

function receiptUrlForTenant(Tenant $tenant, Order $order): string
{
    return 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain').'/admin/orders/'.$order->id.'/receipt';
}

function receiptTenant(array $overrides = []): Tenant
{
    $tenant = actingAsTenant($overrides);

    if (Plan::query()->doesntExist()) {
        seedBootstrapPlans();
    }

    $plan = Plan::query()->firstOrFail();
    TenantSubscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
        'current_period_starts_at' => now()->subDay(),
        'current_period_ends_at' => now()->addMonth(),
        'plan_name' => $plan->name,
        'billing_period' => $plan->billing_period,
        'price' => $plan->price,
        'max_products' => $plan->max_products,
        'max_staff' => $plan->max_staff,
        'custom_domain_allowed' => $plan->custom_domain_allowed,
    ]);

    return $tenant;
}

/**
 * @return array{0: Order, 1: ProductVariant}
 */
function receiptMakeOrder(array $overrides = [], int $quantity = 1): array
{
    [$cart, $variant] = createCartWithVariant($quantity);
    $order = app(OrderService::class)->createFromCart($cart, array_merge([
        'guest_name' => 'Receipt Guest',
        'guest_email' => 'receipt@example.com',
        'guest_phone' => '01700000000',
        'shipping_address' => [
            'recipient_name' => 'Receipt Guest',
            'phone' => '01700000000',
            'address_line_1' => '1 Snapshot Street',
            'city' => 'Dhaka',
            'postal_code' => '1200',
            'country' => 'Bangladesh',
        ],
        'billing_address' => [
            'recipient_name' => 'Receipt Guest',
            'address_line_1' => '1 Billing Street',
            'city' => 'Dhaka',
            'country' => 'Bangladesh',
        ],
        'shipping_cost' => 1500,
        'tax_total' => 500,
    ], $overrides));

    return [$order, $variant];
}

/**
 * @return array{0: Order, 1: ProductVariant}
 */
function receiptMakeSerializedOrder(): array
{
    $variant = createTestVariant(['inventory_type' => 'serialized']);
    SerialNumber::factory()->count(2)->for($variant, 'variant')->create(['status' => 'available']);

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
        'guest_name' => 'Serialized Guest',
        'guest_email' => 'serialized@example.com',
        'guest_phone' => '01800000000',
    ]);

    return [$order, $variant];
}

function receiptStoreUser(Tenant $tenant): User
{
    return User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'staff',
        'is_active' => true,
    ]);
}

describe('ORDER RECEIPT access', function (): void {
    it('allows an authorized tenant user to open the receipt', function (): void {
        $tenant = receiptTenant(['name' => 'Receipt Mobile Shop']);
        [$order] = receiptMakeOrder();
        $user = receiptStoreUser($tenant);

        $this->actingAs($user)
            ->get(receiptUrlForTenant($tenant, $order))
            ->assertOk();
    });

    it('rejects an unauthenticated receipt request', function (): void {
        $tenant = receiptTenant();
        [$order] = receiptMakeOrder();

        $this->get(receiptUrlForTenant($tenant, $order))
            ->assertForbidden();
    });

    it('rejects cross-tenant receipt access', function (): void {
        $tenantA = receiptTenant();
        [$order] = receiptMakeOrder();
        $userA = receiptStoreUser($tenantA);

        $tenantB = receiptTenant();

        $this->actingAs($userA)
            ->get(receiptUrlForTenant($tenantB, $order))
            ->assertNotFound();
    });

    it('does not grant direct platform-admin access without support mode', function (): void {
        $tenant = receiptTenant();
        [$order] = receiptMakeOrder();
        $platformAdmin = User::factory()->create([
            'tenant_id' => null,
            'is_platform_admin' => true,
            'is_active' => true,
        ]);

        $this->actingAs($platformAdmin)
            ->get(receiptUrlForTenant($tenant, $order))
            ->assertNotFound();
    });
});

describe('ORDER RECEIPT content and historical data', function (): void {
    it('renders store, order, customer, address, totals, and payment history', function (): void {
        $tenant = receiptTenant([
            'name' => 'Snapshot Mobile Shop',
            'contact_phone' => '01900000000',
            'contact_email' => 'shop@example.com',
        ]);
        $tenant->themeSettings()->updateOrCreate([], ['logo_path' => 'tenant-logos/snapshot.png']);
        $tenant->settings()->updateOrCreate([], ['order_confirmation_note' => 'Thank you for shopping with us.']);
        [$order] = receiptMakeOrder();
        $method = PaymentMethod::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Cash on Delivery',
            'type' => PaymentMethodType::Cod,
            'is_active' => true,
        ]);
        app(OrderService::class)->recordPayment($order, $method, 50000, OrderPaymentStatus::Paid, 'RCPT-123');
        $user = receiptStoreUser($tenant);

        $response = $this->actingAs($user)->get(receiptUrlForTenant($tenant, $order));

        $response->assertOk()
            ->assertSee('Snapshot Mobile Shop')
            ->assertSee('01900000000')
            ->assertSee('shop@example.com')
            ->assertSee($order->order_number)
            ->assertSee('Receipt Guest')
            ->assertSee('1 Snapshot Street')
            ->assertSee('1 Billing Street')
            ->assertSee('Subtotal')
            ->assertSee('Discount / coupon')
            ->assertSee('Shipping')
            ->assertSee('Tax')
            ->assertSee('Grand total')
            ->assertSee('Amount paid')
            ->assertSee('Amount due')
            ->assertSee('Cash on Delivery')
            ->assertSee('RCPT-123')
            ->assertSee('Thank you for shopping with us.')
            ->assertSee('Print Receipt');
    });

    it('renders exact linked serials and keeps non-serialized items free of serial values', function (): void {
        $tenant = receiptTenant();
        [$order] = receiptMakeSerializedOrder();
        app(OrderService::class)->updateStatus($order, OrderStatus::Confirmed);
        $serials = SerialNumber::query()
            ->where('order_item_id', $order->items()->first()->id)
            ->pluck('imei_or_serial');
        $user = receiptStoreUser($tenant);

        $response = $this->actingAs($user)->get(receiptUrlForTenant($tenant, $order));

        $response->assertOk();
        foreach ($serials as $serial) {
            $response->assertSee($serial);
        }

        [$regularOrder] = receiptMakeOrder();
        $regularResponse = $this->actingAs($user)->get(receiptUrlForTenant($tenant, $regularOrder));
        $regularResponse->assertOk()->assertDontSee('Serial / IMEI');
    });

    it('uses the historical order item price after the current catalog price changes', function (): void {
        $tenant = receiptTenant();
        [$order, $variant] = receiptMakeOrder();
        $historicalPrice = $order->items()->first()->unit_price;
        $variant->update(['price' => $historicalPrice + 500000]);
        $user = receiptStoreUser($tenant);

        $this->actingAs($user)
            ->get(receiptUrlForTenant($tenant, $order))
            ->assertOk()
            ->assertSee(currency_symbol('BDT').number_format($historicalPrice / 100, 2));
    });

    it('renders partial, empty-payment, and cancelled orders safely', function (): void {
        $tenant = receiptTenant();
        [$partial] = receiptMakeOrder();
        $method = PaymentMethod::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Manual Payment',
            'type' => PaymentMethodType::Cod,
            'is_active' => true,
        ]);
        app(OrderService::class)->recordPayment($partial, $method, 10000, OrderPaymentStatus::Paid);
        [$cancelled] = receiptMakeOrder();
        app(OrderService::class)->cancelOrder($cancelled, 'Customer request');
        [$noBilling] = receiptMakeOrder(['billing_address' => null]);
        $user = receiptStoreUser($tenant);

        $this->actingAs($user)
            ->get(receiptUrlForTenant($tenant, $partial))
            ->assertOk()
            ->assertSee('Amount due');
        $this->actingAs($user)
            ->get(receiptUrlForTenant($tenant, $cancelled))
            ->assertOk()
            ->assertSee('Cancelled')
            ->assertSee('No payments recorded.');
        $this->actingAs($user)
            ->get(receiptUrlForTenant($tenant, $noBilling))
            ->assertOk()
            ->assertDontSee('Billing address');
    });
});

describe('ORDER RECEIPT print presentation', function (): void {
    it('is standalone, includes browser print support, and omits admin navigation', function (): void {
        $tenant = receiptTenant();
        [$order] = receiptMakeOrder();
        $user = receiptStoreUser($tenant);
        $updatedAt = $order->fresh()->updated_at?->toISOString();
        $eventCount = $order->events()->count();
        $paymentCount = $order->payments()->count();

        $this->actingAs($user)
            ->get(receiptUrlForTenant($tenant, $order))
            ->assertOk()
            ->assertSee('@media print', false)
            ->assertSee('window.print()', false)
            ->assertSee('Back to Order')
            ->assertDontSee('fi-sidebar', false)
            ->assertDontSee('Filament', false);

        expect($order->fresh()->updated_at?->toISOString())->toBe($updatedAt)
            ->and($order->events()->count())->toBe($eventCount)
            ->and($order->payments()->count())->toBe($paymentCount);
    });
});
