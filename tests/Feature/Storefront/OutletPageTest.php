<?php

declare(strict_types=1);

use App\Models\Outlet;
use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function outletTenant(string $subdomain): Tenant
{
    return actingAsTenant(['subdomain' => $subdomain]);
}

function outletFor(object $tenant, array $overrides = []): Outlet
{
    app(Tenancy::class)->set($tenant);

    return Outlet::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'name' => 'Flagship Store',
        'address_line_1' => '123 Main Road',
        'city' => 'Dhaka',
        'phone' => '01700000000',
        'opening_hours' => ['saturday' => '10:00 AM - 8:00 PM', 'friday' => '2:00 PM - 8:00 PM'],
        'is_active' => true,
    ], $overrides));
}

it('lists active outlets on the storefront page', function (): void {
    $tenant = outletTenant('outlet-shop');
    outletFor($tenant, ['name' => 'Dhanmondi Branch', 'sort_order' => 2]);
    outletFor($tenant, ['name' => 'Uttara Branch', 'sort_order' => 1]);

    $html = get('http://outlet-shop.'.config('tenancy.central_domain').'/outlets')->assertOk()->getContent();

    expect($html)->toContain('Uttara Branch')
        ->and($html)->toContain('Dhanmondi Branch')
        ->and($html)->toContain('123 Main Road')
        ->and($html)->toContain('10:00 AM - 8:00 PM');
});

it('hides inactive outlets from the storefront', function (): void {
    $tenant = outletTenant('outlet-hide');
    outletFor($tenant, ['name' => 'Visible Outlet']);
    outletFor($tenant, ['name' => 'Hidden Outlet', 'is_active' => false]);

    $html = get('http://outlet-hide.'.config('tenancy.central_domain').'/outlets')->assertOk()->getContent();

    expect($html)->toContain('Visible Outlet')
        ->and($html)->not->toContain('Hidden Outlet');
});

it('never leaks another tenant\'s outlets', function (): void {
    outletTenant('outlet-a');
    $other = outletTenant('outlet-b');
    outletFor($other, ['name' => 'Foreign Outlet']);

    $html = get('http://outlet-a.'.config('tenancy.central_domain').'/outlets')->assertOk()->getContent();

    expect($html)->not->toContain('Foreign Outlet');
});

it('renders a map link when coordinates are present', function (): void {
    $tenant = outletTenant('outlet-map');
    outletFor($tenant, ['latitude' => '23.8103000', 'longitude' => '90.4125000']);

    $html = get('http://outlet-map.'.config('tenancy.central_domain').'/outlets')->assertOk()->getContent();

    expect($html)->toContain('https://www.google.com/maps?q=23.8103,90.4125');
});

it('auto-generates a slug and scopes it per tenant', function (): void {
    $tenant = outletTenant('outlet-slug');

    $a = outletFor($tenant, ['name' => 'Main Store']);

    expect($a->slug)->toBe('main-store');
});
