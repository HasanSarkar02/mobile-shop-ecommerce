<?php

declare(strict_types=1);

use App\Models\BdDistrict;
use App\Models\BdDivision;
use App\Models\BdUpazila;
use App\Models\ShippingMethod;
use App\Models\TenantShippingRate;
use App\Services\ShippingService;

beforeEach(function (): void {
    actingAsTenant();
});

function shippingCreateDivision(string $name = 'Dhaka Div'): BdDivision
{
    return BdDivision::query()->create([
        'name_en' => $name.' '.uniqid(),
        'name_bn' => $name,
        'bbs_code' => 'bbs-'.uniqid(),
    ]);
}

function shippingCreateDistrict(BdDivision $division, string $name = 'Dhaka Dist'): BdDistrict
{
    return BdDistrict::query()->create([
        'division_id' => $division->id,
        'name_en' => $name.' '.uniqid(),
        'name_bn' => $name,
        'bbs_code' => 'b-'.uniqid(),
    ]);
}

function shippingCreateUpazila(BdDistrict $district, string $name = 'Savar'): BdUpazila
{
    return BdUpazila::query()->create([
        'district_id' => $district->id,
        'name_en' => $name.' '.uniqid(),
        'name_bn' => $name,
    ]);
}

it('returns exact upazila rate when matched', function (): void {
    $division = shippingCreateDivision();
    $district = shippingCreateDistrict($division);
    $upazila = shippingCreateUpazila($district);

    TenantShippingRate::query()->create([
        'name' => 'Upazila Rate',
        'bd_division_id' => null,
        'bd_district_id' => null,
        'bd_upazila_id' => $upazila->id,
        'charge' => 8000,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $service = app(ShippingService::class);

    expect($service->quote($upazila->id, null, null, null, null))->toBe(8000);
});

it('falls back via upazila district when upazila rate missing', function (): void {
    $division = shippingCreateDivision();
    $district = shippingCreateDistrict($division);
    $upazila = shippingCreateUpazila($district);

    TenantShippingRate::query()->create([
        'name' => 'District Rate',
        'bd_district_id' => $district->id,
        'bd_upazila_id' => null,
        'charge' => 7000,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $service = app(ShippingService::class);

    // No upazila-specific row — should resolve to its district (via BdUpazila lookup)
    expect($service->quote($upazila->id, null, null, null, null))->toBe(7000);
});

it('returns exact district rate where upazila is null', function (): void {
    $division = shippingCreateDivision();
    $district = shippingCreateDistrict($division);

    TenantShippingRate::query()->create([
        'name' => 'District Exact',
        'bd_district_id' => $district->id,
        'bd_upazila_id' => null,
        'charge' => 6000,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $service = app(ShippingService::class);

    expect($service->quote(null, $district->id, null, null, null))->toBe(6000);
});

it('falls back to generic district rate when exact district missing', function (): void {
    $division = shippingCreateDivision();
    $district = shippingCreateDistrict($division);
    $otherUpazila = shippingCreateUpazila($district);

    // Only a district rate that incorrectly carries an upazila (not null) — second query allows any district rate
    TenantShippingRate::query()->create([
        'name' => 'District Generic',
        'bd_district_id' => $district->id,
        'bd_upazila_id' => $otherUpazila->id,
        'charge' => 6500,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $service = app(ShippingService::class);

    expect($service->quote(null, $district->id, null, null, null))->toBe(6500);
});

it('uses division rate when no district match', function (): void {
    $division = shippingCreateDivision();

    TenantShippingRate::query()->create([
        'name' => 'Division Rate',
        'bd_division_id' => $division->id,
        'bd_district_id' => null,
        'bd_upazila_id' => null,
        'charge' => 5000,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $service = app(ShippingService::class);

    expect($service->quote(null, null, $division->id, null, null))->toBe(5000);
});

it('uses fallback outside rate when geo not matched', function (): void {
    TenantShippingRate::query()->create([
        'name' => 'Outside',
        'bd_division_id' => null,
        'bd_district_id' => null,
        'bd_upazila_id' => null,
        'charge' => 12000,
        'is_active' => true,
        'sort_order' => 99,
    ]);

    $service = app(ShippingService::class);

    expect($service->quote(null, null, null, null, null))->toBe(12000);
});

it('falls back to ShippingMethod when no rate exists', function (): void {
    ShippingMethod::query()->create([
        'name' => 'Flat',
        'type' => 'flat_rate',
        'cost' => 1500,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $service = app(ShippingService::class);

    expect($service->quote(null, null, null, null, null))->toBe(1500);

    // Inactive method ignored → 0
    ShippingMethod::query()->update(['is_active' => false]);
    expect($service->quote(null, null, null, null, null))->toBe(0);
});

it('ignores inactive rates', function (): void {
    $division = shippingCreateDivision();
    $district = shippingCreateDistrict($division);

    TenantShippingRate::query()->create([
        'name' => 'Inactive',
        'bd_district_id' => $district->id,
        'bd_upazila_id' => null,
        'charge' => 9999,
        'is_active' => false,
        'sort_order' => 0,
    ]);
    TenantShippingRate::query()->create([
        'name' => 'Active',
        'bd_district_id' => $district->id,
        'bd_upazila_id' => null,
        'charge' => 6000,
        'is_active' => true,
        'sort_order' => 10,
    ]);

    $service = app(ShippingService::class);

    expect($service->quote(null, $district->id, null, null, null))->toBe(6000);
});

it('respects sort_order tie-break', function (): void {
    $division = shippingCreateDivision();
    $district = shippingCreateDistrict($division);

    TenantShippingRate::query()->create([
        'name' => 'Second',
        'bd_district_id' => $district->id,
        'bd_upazila_id' => null,
        'charge' => 9000,
        'is_active' => true,
        'sort_order' => 20,
    ]);
    TenantShippingRate::query()->create([
        'name' => 'First',
        'bd_district_id' => $district->id,
        'bd_upazila_id' => null,
        'charge' => 4000,
        'is_active' => true,
        'sort_order' => 5,
    ]);

    $service = app(ShippingService::class);

    expect($service->quote(null, $district->id, null, null, null))->toBe(4000);
});

it('applies free_threshold post-discount and handles null subtotal', function (): void {
    $division = shippingCreateDivision();
    $district = shippingCreateDistrict($division);

    TenantShippingRate::query()->create([
        'name' => 'Thresh',
        'bd_district_id' => $district->id,
        'bd_upazila_id' => null,
        'charge' => 8000,
        'free_threshold' => 100000,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $service = app(ShippingService::class);

    expect($service->quote(null, $district->id, null, null, 120000))->toBe(0)
        ->and($service->quote(null, $district->id, null, null, 80000))->toBe(8000)
        ->and($service->quote(null, $district->id, null, null, null))->toBe(8000);
});

it('sanitizes empty string ids in quoteForGuest', function (): void {
    $division = shippingCreateDivision();
    $district = shippingCreateDistrict($division);
    $upazila = shippingCreateUpazila($district);

    TenantShippingRate::query()->create([
        'name' => 'Division Rate',
        'bd_division_id' => $division->id,
        'bd_district_id' => null,
        'bd_upazila_id' => null,
        'charge' => 5000,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $service = app(ShippingService::class);

    // Empty strings should be treated as null → fallback to division not outside
    $charge = $service->quoteForGuest([
        'bd_upazila_id' => '',
        'bd_district_id' => '',
        'bd_division_id' => (string) $division->id,
    ], null);

    expect($charge)->toBe(5000)
        ->and($service->quote($upazila->id, null, null, null, null))->toBe(5000); // via district fallback chain
});
