<?php

declare(strict_types=1);

use App\Models\Faq;
use App\Models\StaticPage;
use App\Support\Tenancy\Tenancy;
use Database\Seeders\TrustContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the 8 policy pages and 6 FAQs per tenant', function (): void {
    $tenant = actingAsTenant();

    (new TrustContentSeeder)->run($tenant);
    app(Tenancy::class)->set($tenant);

    expect(StaticPage::query()->count())->toBe(8)
        ->and(Faq::query()->whereNull('product_id')->count())->toBe(6);

    foreach (['delivery-policy', 'warranty-policy', 'return-policy', 'exchange-policy', 'refund-policy', 'privacy-policy', 'emi-payment-policy', 'pre-order-policy'] as $slug) {
        $page = StaticPage::query()->where('slug', $slug)->first();
        expect($page)->not->toBeNull("missing {$slug}")
            ->and($page->status->value)->toBe('published')
            ->and($page->show_in_footer)->toBeTrue()
            ->and($page->content)->not->toBeEmpty();
    }
});

it('is idempotent — re-running does not duplicate content', function (): void {
    $tenant = actingAsTenant();

    (new TrustContentSeeder)->run($tenant);
    (new TrustContentSeeder)->run($tenant);
    app(Tenancy::class)->set($tenant);

    expect(StaticPage::query()->count())->toBe(8)
        ->and(Faq::query()->count())->toBe(6);
});

it('keeps policy content tenant isolated', function (): void {
    $tenantA = actingAsTenant(['subdomain' => 'trust-a']);
    (new TrustContentSeeder)->run($tenantA);
    app(Tenancy::class)->set($tenantA);
    expect(StaticPage::query()->count())->toBe(8);

    // Tenant B starts with zero policies and gets its own full set.
    $tenantB = actingAsTenant(['subdomain' => 'trust-b']);
    expect(StaticPage::query()->count())->toBe(0);

    (new TrustContentSeeder)->run($tenantB);
    app(Tenancy::class)->set($tenantB);
    expect(StaticPage::query()->count())->toBe(8);

    app(Tenancy::class)->set($tenantA);
    expect(Faq::query()->count())->toBe(6);
});
