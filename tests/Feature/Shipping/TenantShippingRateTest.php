<?php

declare(strict_types=1);

use App\Models\BdDistrict;
use App\Models\BdDivision;
use App\Models\BdUpazila;
use App\Models\TenantShippingRate;
use App\Services\ShippingService;
use App\Support\Tenancy\Tenancy;

beforeEach(function (): void {
    actingAsTenant();
});

function rateCreateDivision(string $name = 'Rate Div'): BdDivision
{
    return BdDivision::query()->create([
        'name_en' => $name.' '.uniqid(),
        'name_bn' => $name,
        'bbs_code' => 'rate-bbs-'.uniqid(),
    ]);
}

function rateCreateDistrict(BdDivision $division, string $name = 'Rate Dist'): BdDistrict
{
    return BdDistrict::query()->create([
        'division_id' => $division->id,
        'name_en' => $name.' '.uniqid(),
        'name_bn' => $name,
        'bbs_code' => 'rate-d-'.uniqid(),
    ]);
}

function rateCreateUpazila(BdDistrict $district, string $name = 'Rate Upazila'): BdUpazila
{
    return BdUpazila::query()->create([
        'district_id' => $district->id,
        'name_en' => $name.' '.uniqid(),
        'name_bn' => $name,
    ]);
}

it('isolates shipping rates strictly per tenant', function (): void {
    $tenantA = tenant();
    $division = rateCreateDivision();
    $district = rateCreateDistrict($division);

    TenantShippingRate::query()->create([
        'name' => 'Inside Dhaka A',
        'bd_district_id' => $district->id,
        'bd_upazila_id' => null,
        'charge' => 6000,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $service = app(ShippingService::class);
    expect($service->quote(null, $district->id, null, null, null))->toBe(6000);

    $tenantB = actingAsTenant(['subdomain' => 'tenant-b-'.uniqid()]);
    // Tenant B sees no rate for same district (isolation via BelongsToTenant)
    expect($service->quote(null, $district->id, null, null, null))->toBe(0);

    TenantShippingRate::query()->create([
        'name' => 'Inside Dhaka B',
        'bd_district_id' => $district->id,
        'bd_upazila_id' => null,
        'charge' => 12000,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    expect($service->quote(null, $district->id, null, null, null))->toBe(12000);

    // Switch back to A — still 6000, not polluted by B
    app(Tenancy::class)->set($tenantA);
    expect($service->quote(null, $district->id, null, null, null))->toBe(6000);

    app(Tenancy::class)->set($tenantB);
});

it('stores charge and free_threshold as minor units (BDT * 100)', function (): void {
    $division = rateCreateDivision();
    $district = rateCreateDistrict($division);

    $rate = TenantShippingRate::query()->create([
        'name' => 'Charge Conversion',
        'bd_district_id' => $district->id,
        'bd_upazila_id' => null,
        'charge' => 8000, // 80 BDT
        'free_threshold' => 100000, // 1000 BDT
        'is_active' => true,
        'sort_order' => 1,
    ]);

    // Model stores minor units
    expect($rate->fresh()->charge)->toBe(8000)
        ->and($rate->fresh()->free_threshold)->toBe(100000);

    // Presentation via money helper (as TenantShippingRateResource table does)
    expect(money($rate->charge))->toBe('৳80.00')
        ->and(money($rate->free_threshold))->toBe('৳1,000.00');

    $service = app(ShippingService::class);

    // Below threshold → charge, above → free (0)
    expect($service->quote(null, $district->id, null, null, 50000))->toBe(8000)
        ->and($service->quote(null, $district->id, null, null, 100000))->toBe(0)
        ->and($service->quote(null, $district->id, null, null, 150000))->toBe(0);
});

it('excludes inactive rates from quote', function (): void {
    $division = rateCreateDivision();
    $district = rateCreateDistrict($division);

    TenantShippingRate::query()->create([
        'name' => 'Inactive Rate',
        'bd_district_id' => $district->id,
        'bd_upazila_id' => null,
        'charge' => 9999,
        'is_active' => false,
        'sort_order' => 0,
    ]);

    // No active rate → fallback 0 (no ShippingMethod either)
    $service = app(ShippingService::class);
    expect($service->quote(null, $district->id, null, null, null))->toBe(0);

    TenantShippingRate::query()->create([
        'name' => 'Active Rate',
        'bd_district_id' => $district->id,
        'bd_upazila_id' => null,
        'charge' => 6000,
        'is_active' => true,
        'sort_order' => 10,
    ]);

    expect($service->quote(null, $district->id, null, null, null))->toBe(6000);
});
