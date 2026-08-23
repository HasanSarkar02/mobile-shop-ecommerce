<?php

declare(strict_types=1);

use App\Enums\CampaignStatus;
use App\Models\Banner;
use App\Models\Campaign;
use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function offerTenant(string $subdomain): Tenant
{
    return actingAsTenant(['subdomain' => $subdomain]);
}

function offerCampaignFor(object $tenant, array $overrides = []): Campaign
{
    app(Tenancy::class)->set($tenant);

    return Campaign::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'name' => 'Summer Sale',
        'status' => CampaignStatus::Active,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(7),
    ], $overrides));
}

it('lists active in-window campaigns on /offers', function (): void {
    $tenant = offerTenant('offer-list');
    offerCampaignFor($tenant, ['name' => 'Live Offer']);
    offerCampaignFor($tenant, ['name' => 'Future Offer', 'starts_at' => now()->addDays(2)]);
    offerCampaignFor($tenant, ['name' => 'Ended Offer', 'ends_at' => now()->subDay()]);
    offerCampaignFor($tenant, ['name' => 'Draft Offer', 'status' => CampaignStatus::Draft]);

    $html = get('http://offer-list.'.config('tenancy.central_domain').'/offers')->assertOk()->getContent();

    expect($html)->toContain('Live Offer')
        ->and($html)->not->toContain('Future Offer')
        ->and($html)->not->toContain('Ended Offer')
        ->and($html)->not->toContain('Draft Offer');
});

it('shows an eligible campaign detail page with countdown and banners', function (): void {
    $tenant = offerTenant('offer-show');
    $campaign = offerCampaignFor($tenant);
    Banner::query()->create([
        'tenant_id' => $tenant->id,
        'campaign_id' => $campaign->id,
        'title' => 'Summer hero',
        'is_active' => true,
    ]);

    $html = get('http://offer-show.'.config('tenancy.central_domain').'/offer/summer-sale')->assertOk()->getContent();

    expect($html)->toContain('Summer Sale')
        ->and($html)->toContain('offerCountdown')
        ->and($html)->toContain('Ends in');
});

it('404s a draft or ended campaign on the detail page', function (): void {
    $tenant = offerTenant('offer-404');
    offerCampaignFor($tenant, ['slug' => 'draft-one', 'status' => CampaignStatus::Draft]);
    offerCampaignFor($tenant, ['slug' => 'ended-one', 'ends_at' => now()->subHour()]);

    get('http://offer-404.'.config('tenancy.central_domain').'/offer/draft-one')->assertNotFound();
    get('http://offer-404.'.config('tenancy.central_domain').'/offer/ended-one')->assertNotFound();
});

it('hides countdown when campaign has no expiry', function (): void {
    $tenant = offerTenant('offer-noexp');
    offerCampaignFor($tenant, ['name' => 'Always On', 'ends_at' => null]);

    $html = get('http://offer-noexp.'.config('tenancy.central_domain').'/offer/always-on')->assertOk()->getContent();

    expect($html)->toContain('Always On')
        ->and($html)->not->toContain('offerCountdown');
});

it('never leaks another tenant\'s campaigns', function (): void {
    $tenantA = offerTenant('offer-leak-a');
    offerCampaignFor($tenantA, ['name' => 'Tenant A Sale']);

    $tenantB = offerTenant('offer-leak-b');

    $html = get('http://offer-leak-b.'.config('tenancy.central_domain').'/offers')->assertOk()->getContent();

    expect($html)->not->toContain('Tenant A Sale');

    get('http://offer-leak-b.'.config('tenancy.central_domain').'/offer/summer-sale')->assertNotFound();
});
