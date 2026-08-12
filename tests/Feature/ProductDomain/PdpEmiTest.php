<?php

declare(strict_types=1);

use App\Models\EmiPlan;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use Illuminate\Testing\TestResponse;

/**
 * Renders a published product page through the tenant subdomain, returning the
 * server-rendered HTML.
 */
function emiPdpGet(string $slug, object $tenant): TestResponse
{
    return test()->get('http://'.$tenant->subdomain.'.'.config('tenancy.central_domain').'/product/'.$slug);
}

/**
 * Builds a published product with one variant at the given price (cents).
 * Returns [$product, $translation, $tenant].
 */
function emiPdpProduct(int $price = 3000000): array
{
    $tenant = actingAsTenant();

    $product = Product::factory()->create(['status' => 'published']);
    $translation = ProductTranslation::factory()->for($product)->create([
        'locale' => 'en',
        'description' => '<h2>Key Features</h2><p>All-day battery.</p>',
    ]);

    $variant = ProductVariant::factory()->for($product)->create(['price' => $price]);
    app(InventoryService::class)->restock($variant, 10);

    return [$product, $translation, $tenant];
}

/**
 * Creates an EmiPlan for the current tenant and attaches it to the product.
 */
function emiAttachPlan(Product $product, array $attributes = []): EmiPlan
{
    $plan = EmiPlan::create(array_merge([
        'bank_name' => 'City Bank',
        'tenure_months' => 12,
        'interest_rate' => 0,
        'active' => true,
    ], $attributes));

    $product->emiPlans()->attach($plan);

    return $plan;
}

it('renders no EMI UI when the product has no active EMI plans', function (): void {
    [$product, $translation, $tenant] = emiPdpProduct();

    $html = emiPdpGet($translation->slug, $tenant)->assertOk()->getContent();

    expect($html)->not->toContain('EMI from');
    expect($html)->not->toContain('View plans');
    expect($html)->not->toContain('aria-labelledby="emi-modal-title"');
});

it('ignores EMI plans that are not active', function (): void {
    [$product, $translation, $tenant] = emiPdpProduct();

    emiAttachPlan($product, ['active' => false]);

    $html = emiPdpGet($translation->slug, $tenant)->assertOk()->getContent();

    expect($html)->not->toContain('EMI from');
    expect($html)->not->toContain('aria-labelledby="emi-modal-title"');
});

it('renders a compact server-side EMI teaser for an active plan', function (): void {
    [$product, $translation, $tenant] = emiPdpProduct(3000000);

    emiAttachPlan($product, ['tenure_months' => 12, 'interest_rate' => 0]);

    $html = emiPdpGet($translation->slug, $tenant)->assertOk()->getContent();

    // 3,000,000 cents at 0% over 12 months => 250,000 cents/month.
    expect($html)->toContain('EMI from');
    expect($html)->toContain('৳2,500/month');
    expect($html)->toContain('View plans');
    expect($html)->toContain('aria-haspopup="dialog"');
});

it('shows the 0% EMI indicator for zero-interest plans', function (): void {
    [$product, $translation, $tenant] = emiPdpProduct();

    emiAttachPlan($product, ['interest_rate' => 0]);

    $html = emiPdpGet($translation->slug, $tenant)->assertOk()->getContent();

    expect($html)->toContain('0% EMI available');
    expect($html)->toContain('0% EMI');
    expect($html)->toContain('0% interest');
});

it('renders multiple banks and tenures from the existing relation', function (): void {
    [$product, $translation, $tenant] = emiPdpProduct(3000000);

    emiAttachPlan($product, ['bank_name' => 'City Bank', 'tenure_months' => 12, 'interest_rate' => 0]);
    emiAttachPlan($product, ['bank_name' => 'DBBL', 'tenure_months' => 6, 'interest_rate' => 10]);

    $html = emiPdpGet($translation->slug, $tenant)->assertOk()->getContent();

    expect($html)->toContain('City Bank');
    expect($html)->toContain('DBBL');
    expect($html)->toContain('for 12 months');
    expect($html)->toContain('for 6 months');
    expect($html)->toContain('10% interest');
});

it('calculates the monthly installment with the existing formula', function (): void {
    [$product, $translation, $tenant] = emiPdpProduct(3000000);

    emiAttachPlan($product, ['tenure_months' => 6, 'interest_rate' => 10]);

    $html = emiPdpGet($translation->slug, $tenant)->assertOk()->getContent();

    // round(3,000,000 * (1 + 10/100) / 6) = 550,000 cents/month; total 3,300,000 cents.
    expect($html)->toContain('৳5,500/month');
    expect($html)->toContain('৳33,000</span>');

    // The reactive binding keeps the exact same formula on the client side.
    expect($html)->toContain('Math.round(price * (1 + rate / 100) / tenure)');
    expect($html)->toContain('emiMonthly((current()?.price ?? 0)');
});

it('recomputes EMI figures from the currently selected variant price', function (): void {
    [$product, $translation, $tenant] = emiPdpProduct(3000000);

    // Second variant at twice the price -> client-side recompute must react.
    ProductVariant::factory()->for($product)->create(['price' => 6000000]);
    app(InventoryService::class)->restock($product->variants->last(), 10);

    emiAttachPlan($product, ['tenure_months' => 12, 'interest_rate' => 0]);

    $html = emiPdpGet($translation->slug, $tenant)->assertOk()->getContent();

    // The SSR teaser baseline uses the same variant the page starts on (the
    // variants relation order is DB-defined, so mirror the page precisely).
    $fresh = Product::query()->with('variants')->find($product->id);
    $basePrice = $fresh->variants->first()?->price ?? 0;
    $expectedMonthly = (int) round($basePrice * 1.0 / 12);

    expect($html)->toContain('৳'.number_format($expectedMonthly / 100).'/month');

    // Both prices are in the Alpine variant payload for the recompute (@js
    // serializes its JSON through JSON.parse() with \u0022 escapes).
    expect($html)->toContain('\u0022price\u0022:3000000');
    expect($html)->toContain('\u0022price\u0022:6000000');

    // Teaser + modal figures are driven by current().
    expect($html)->toContain('x-text="emiHeadline()"');
    expect($html)->toContain('emiMonthly((current()?.price ?? 0)');
});

it('renders the accessible EMI dialog attributes', function (): void {
    [$product, $translation, $tenant] = emiPdpProduct();

    emiAttachPlan($product);

    $html = emiPdpGet($translation->slug, $tenant)->assertOk()->getContent();

    expect($html)->toContain('<template x-teleport="body">');
    expect($html)->toContain('role="dialog"');
    expect($html)->toContain('aria-modal="true"');
    expect($html)->toContain('aria-labelledby="emi-modal-title"');
    expect($html)->toContain('id="emi-modal-title"');
    expect($html)->toContain('aria-label="Close EMI plans"');
    expect($html)->toContain('@keydown.escape.window="closeEmi()"');
    expect($html)->toContain('@click="closeEmi()"');
    expect($html)->toContain('x-ref="emiClose"');
    expect($html)->toContain('x-ref="emiPanel"');

    // Focus moves to the close button on open and returns to the trigger.
    expect($html)->toContain('$refs.emiClose?.focus()');
    expect($html)->toContain('this.emiTrigger?.focus?.()');

    // Scrollable panel so long plan lists fit the viewport.
    expect($html)->toContain('max-h-[85vh]');
    expect($html)->toContain('overflow-y-auto');
});

it('keeps the basic EMI information in the server-rendered HTML', function (): void {
    [$product, $translation, $tenant] = emiPdpProduct(3000000);

    emiAttachPlan($product, ['bank_name' => 'City Bank', 'tenure_months' => 12, 'interest_rate' => 12]);

    $html = emiPdpGet($translation->slug, $tenant)->assertOk()->getContent();

    // The plan list is in the raw payload (bank, tenure, rate), not behind JS.
    expect($html)->toContain('City Bank');
    expect($html)->toContain('12 months');
    expect($html)->toContain('12% interest');

    // Monthly + total are pre-computed for the SSR baseline.
    expect($html)->toContain('৳2,800/month');
    expect($html)->toContain('৳33,600</span>');
});
