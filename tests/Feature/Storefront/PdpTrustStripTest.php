<?php

declare(strict_types=1);

use App\Enums\PaymentMethodType;
use App\Enums\ShippingMethodType;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Services\InventoryService;
use App\Support\Tenancy\Tenancy;

function trustStripBase(object $tenant): string
{
    return 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain');
}

function trustStripProduct(): array
{
    $tenant = actingAsTenant(['status' => 'active']);
    $product = Product::factory()->create(['status' => 'published']);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    $variant = ProductVariant::factory()->for($product)->create();
    app(InventoryService::class)->restock($variant, 10);

    $slug = $product->translation('en')->slug;

    return [$slug, $tenant, $variant, trustStripBase($tenant)];
}

it('renders the variant-agnostic trust strip with active shipping methods', function (): void {
    [$slug, $tenant, $variant, $base] = trustStripProduct();

    $shipping = ShippingMethod::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Express Delivery',
        'type' => ShippingMethodType::FlatRate,
        'cost' => 12000,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    expect($html)->toContain('Delivery');
    expect($html)->toContain('Express Delivery');
    expect($html)->toContain('৳120');
    expect($html)->toContain('Delivery and payment information');
});

it('marks free shipping methods as Free in the trust strip', function (): void {
    [$slug, $tenant, $variant, $base] = trustStripProduct();

    ShippingMethod::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Standard Shipping',
        'type' => ShippingMethodType::Free,
        'cost' => 0,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    expect($html)->toContain('Standard Shipping');
    expect($html)->toContain('>Free</span>');
});

it('does not render inactive shipping methods', function (): void {
    [$slug, $tenant, $variant, $base] = trustStripProduct();

    ShippingMethod::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Retired Courier',
        'type' => ShippingMethodType::FlatRate,
        'cost' => 5000,
        'is_active' => false,
        'sort_order' => 1,
    ]);

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    expect($html)->not->toContain('Retired Courier');
});

it('renders active payment methods in the trust strip', function (): void {
    [$slug, $tenant, $variant, $base] = trustStripProduct();

    PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Cash on Delivery',
        'type' => PaymentMethodType::Cod,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    expect($html)->toContain('Payment');
    expect($html)->toContain('Cash on Delivery');
});

it('does not render inactive payment methods', function (): void {
    [$slug, $tenant, $variant, $base] = trustStripProduct();

    PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Retired Gateway',
        'type' => PaymentMethodType::Aggregator,
        'is_active' => false,
        'sort_order' => 1,
    ]);

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    expect($html)->not->toContain('Retired Gateway');
});

it('omits the trust strip entirely when no shipping or payment methods exist', function (): void {
    [$slug, $tenant, $variant, $base] = trustStripProduct();

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    expect($html)->not->toContain('Delivery and payment information');
    expect($html)->not->toContain('>Delivery</p>');
});

it('keeps the trust strip variant-agnostic (no expected_available_at leakage)', function (): void {
    [$slug, $tenant, $variant, $base] = trustStripProduct();

    ShippingMethod::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Standard Shipping',
        'type' => ShippingMethodType::Free,
        'cost' => 0,
        'is_active' => true,
        'sort_order' => 1,
    ]);
    PaymentMethod::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Cash on Delivery',
        'type' => PaymentMethodType::Cod,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $variant->update([
        'expected_available_at' => now()->addDays(3),
        'availability' => 'out_of_stock',
    ]);
    app(Tenancy::class)->set($tenant);

    $html = $this->get($base.'/product/'.$slug)->assertOk()->getContent();

    // The dynamic restock messaging belongs to the purchase-state block and is
    // sent to the client only inside the serialized variant data.
    expect($html)->toContain('expected_available_at');

    // The trust strip itself is rendered from the same static server block and
    // must not interpolate the variant date.
    preg_match('/aria-label="Delivery and payment information">(.*?)<\/ul>\s*<\/div>/s', $html, $matches);
    expect($matches)->not->toBeEmpty();
    expect($matches[1])->not->toContain('Ships by');
    expect($matches[1])->not->toContain('Expected availability');
    expect($matches[1])->not->toContain($variant->expected_available_at->format('M j, Y'));
});
