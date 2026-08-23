<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Support\WhatsApp;

/**
 * Renders the storefront home through the tenant subdomain.
 */
function waHome(object $tenant): string
{
    return test()->get('http://'.$tenant->subdomain.'.'.config('tenancy.central_domain').'/')->getContent();
}

function waConfigure(object $tenant, bool $enabled, ?string $number): void
{
    $tenant->themeSettings->update([
        'whatsapp_widget_enabled' => $enabled,
        'social_links' => ['whatsapp' => $number],
    ]);
}

it('shows the floating widget when enabled and configured', function (): void {
    $tenant = actingAsTenant();
    waConfigure($tenant, enabled: true, number: '01712345678');

    $html = waHome($tenant);

    expect($html)->toContain('wa.me/8801712345678');
    expect($html)->toContain('Chat on WhatsApp');
});

it('hides the widget when disabled even if configured', function (): void {
    $tenant = actingAsTenant();
    waConfigure($tenant, enabled: false, number: '01712345678');

    $html = waHome($tenant);

    expect($html)->not->toContain('wa.me/8801712345678');
    expect($html)->not->toContain('Chat on WhatsApp');
});

it('hides the widget when enabled but no number is configured', function (): void {
    $tenant = actingAsTenant();
    waConfigure($tenant, enabled: true, number: null);

    $html = waHome($tenant);

    expect($html)->not->toContain('wa.me/');
    expect($html)->not->toContain('Chat on WhatsApp');
});

it('normalizes local and pasted wa.me links to international format', function (): void {
    expect(WhatsApp::normalize('01712345678'))->toBe('8801712345678')
        ->and(WhatsApp::normalize('+8801712345678'))->toBe('8801712345678')
        ->and(WhatsApp::normalize('https://wa.me/8801712345678'))->toBe('8801712345678')
        ->and(WhatsApp::normalize('  '))->toBeNull()
        ->and(WhatsApp::url(null))->toBeNull()
        ->and(WhatsApp::url('01712345678', 'Hi, is this in stock?'))
        ->toBe('https://wa.me/8801712345678?text='.rawurlencode('Hi, is this in stock?'));
});

it('keeps the widget setting tenant isolated', function (): void {
    $tenantA = actingAsTenant(['subdomain' => 'shop-a-wa']);
    waConfigure($tenantA, enabled: true, number: '01700000001');

    // Tenant B enables with its own number; A must be unaffected.
    $tenantB = actingAsTenant(['subdomain' => 'shop-b-wa']);
    waConfigure($tenantB, enabled: true, number: '01700000002');

    $htmlA = waHome($tenantA);
    $htmlB = waHome($tenantB);

    expect($htmlA)->toContain('wa.me/8801700000001');
    expect($htmlA)->not->toContain('wa.me/8801700000002');
    expect($htmlB)->toContain('wa.me/8801700000002');
    expect($htmlB)->not->toContain('wa.me/8801700000001');
});

it('renders a product inquiry link on the PDP when the widget is active', function (): void {
    $tenant = actingAsTenant();
    waConfigure($tenant, enabled: true, number: '01712345678');

    $product = Product::factory()->create(['status' => 'published', 'tenant_id' => $tenant->id]);
    ProductTranslation::factory()->for($product)->create(['locale' => 'en']);
    $variant = ProductVariant::factory()->for($product)->create(['price' => 250000]);
    app(InventoryService::class)->restock($variant, 5);

    $slug = $product->translation('en')->slug;
    $html = test()->get('http://'.$tenant->subdomain.'.'.config('tenancy.central_domain').'/product/'.$slug)->getContent();

    expect($html)->toContain('wa.me/8801712345678?text=');
});
