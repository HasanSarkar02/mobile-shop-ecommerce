<?php

declare(strict_types=1);

use App\Models\Order;

/**
 * Confirmation route: /checkout/confirmation/{orderNumber}
 *
 * Verifies the route resolves the order tenant-safely, eager-loads its items,
 * passes both $orderNumber and $order to the view, and never exposes one
 * tenant's order to another tenant.
 */
function makeOrderForTenant(string $subdomain): string
{
    actingAsTenant(['subdomain' => $subdomain, 'name' => 'Shop '.$subdomain]);

    $order = Order::query()->create([
        'order_number' => 'ORD-'.$subdomain.'-0001',
        'invoice_number' => 'INV-'.$subdomain.'-0001',
        'status' => 'pending',
        'order_source' => 'website',
        'sales_channel' => 'online_store',
        'currency_code' => 'BDT',
        'grand_total' => 5500,
        'placed_at' => now(),
    ]);

    $order->items()->create([
        'product_name_snapshot' => 'Widget '.$subdomain,
        'variant_sku_snapshot' => 'VAR-'.$subdomain.'-001',
        'quantity' => 2,
        'unit_price' => 2750,
        'line_total' => 5500,
    ]);

    return $subdomain;
}

it('resolves the order by number, eager-loads items, and renders the summary', function (): void {
    $central = config('tenancy.central_domain');
    $sub = makeOrderForTenant('shop-a');

    $response = $this->get("http://{$sub}.{$central}/checkout/confirmation/ORD-{$sub}-0001");

    $response->assertOk()
        ->assertSeeInOrder(['Thank you!', 'ORD-'.$sub.'-0001'])
        ->assertSee('Widget '.$sub)
        ->assertSee('Total')
        ->assertViewHas('orderNumber', 'ORD-'.$sub.'-0001')
        ->assertViewHas('order', fn (Order $order) => $order->order_number === 'ORD-'.$sub.'-0001'
            && $order->relationLoaded('items'));
});

it('renders the page without a summary for an unknown order number', function (): void {
    $central = config('tenancy.central_domain');
    makeOrderForTenant('shop-a');

    $response = $this->get("http://shop-a.{$central}/checkout/confirmation/ORD-999999999");

    $response->assertOk()
        ->assertSee('Thank you!')
        ->assertDontSee('Widget shop-a');

    expect($response->viewData('order'))->toBeNull();
});

it('does not let one tenant see another tenant\'s order', function (): void {
    $central = config('tenancy.central_domain');
    makeOrderForTenant('shop-a');
    makeOrderForTenant('shop-b');

    // tenant "shop-a" requests shop-b's order number by changing the URL.
    $response = $this->get("http://shop-a.{$central}/checkout/confirmation/ORD-shop-b-0001");

    $response->assertOk()
        ->assertDontSee('Widget shop-b');

    expect($response->viewData('order'))->toBeNull();
});
