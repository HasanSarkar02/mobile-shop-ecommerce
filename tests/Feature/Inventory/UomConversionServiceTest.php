<?php

declare(strict_types=1);

use App\Exceptions\IncompatibleUomException;
use App\Filament\Store\Resources\ProductResource\Pages\CreateProduct;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Inventory\UomConversionService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    actingAsTenant();
});

/**
 * @param  array{code: string, name: string, type: string, factor?: string}  $definition
 */
function makeUom(array $definition): UnitOfMeasure
{
    return UnitOfMeasure::query()->create([
        'code' => $definition['code'],
        'name' => $definition['name'],
        'type' => $definition['type'],
        'base_conversion_factor' => $definition['factor'] ?? '1.000000',
    ]);
}

it('converts weight units through base factors', function (): void {
    $kg = makeUom(['code' => 'kg', 'name' => 'Kilogram', 'type' => 'weight', 'factor' => '1000.000000']);
    $g = makeUom(['code' => 'g2', 'name' => 'Gram', 'type' => 'weight', 'factor' => '1.000000']);

    $service = app(UomConversionService::class);

    expect($service->convert('1.500', $kg, $g))->toBe('1500.000')
        ->and($service->convert('1500.000', $g, $kg))->toBe('1.500')
        ->and($service->convert('0.250', $kg, $kg))->toBe('0.250');
});

it('converts volume and discrete units within their own type', function (): void {
    $l = makeUom(['code' => 'l2', 'name' => 'Litre', 'type' => 'volume', 'factor' => '1000.000000']);
    $ml = makeUom(['code' => 'ml2', 'name' => 'Millilitre', 'type' => 'volume', 'factor' => '1.000000']);
    $dozen = makeUom(['code' => 'dozen2', 'name' => 'Dozen', 'type' => 'discrete', 'factor' => '12.000000']);
    $pcs = makeUom(['code' => 'pcs2', 'name' => 'Pieces', 'type' => 'discrete', 'factor' => '1.000000']);

    $service = app(UomConversionService::class);

    expect($service->convert('2.500', $l, $ml))->toBe('2500.000')
        ->and($service->convert('750.000', $ml, $l))->toBe('0.750')
        ->and($service->convert('1.000', $dozen, $pcs))->toBe('12.000')
        ->and($service->convert('24.000', $pcs, $dozen))->toBe('2.000');
});

it('throws when converting between incompatible types', function (): void {
    $kg = makeUom(['code' => 'kg3', 'name' => 'Kilogram', 'type' => 'weight', 'factor' => '1000.000000']);
    $litre = makeUom(['code' => 'l3', 'name' => 'Litre', 'type' => 'volume']);

    app(UomConversionService::class)->convert('1.000', $kg, $litre);
})->throws(IncompatibleUomException::class);

it('rejects non-numeric and negative quantities', function (): void {
    $a = makeUom(['code' => 'a1', 'name' => 'A', 'type' => 'weight']);
    $b = makeUom(['code' => 'b1', 'name' => 'B', 'type' => 'weight']);

    $service = app(UomConversionService::class);

    expect(fn () => $service->convert('abc', $a, $b))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->convert('-1.000', $a, $b))->toThrow(InvalidArgumentException::class);
});

it('renders the product admin form with uom fields without errors', function (): void {
    $tenant = tenant();

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
    ]);
    $this->actingAs($user);

    Livewire::test(CreateProduct::class)->assertSuccessful();
});

it('saves uom and sales increment from the product form payload shape', function (): void {
    $kg = makeUom(['code' => 'kgf', 'name' => 'Kilogram', 'type' => 'weight']);

    // Simulate exactly what Filament dehydrates for the new fields.
    $product = Product::factory()->create([
        'status' => 'published',
        'uom_id' => $kg->id,
        'sell_by_unit' => '0.500',
    ]);

    expect($product->fresh()->uom_id)->toBe($kg->id)
        ->and($product->fresh()->sell_by_unit)->toBe('0.500')
        ->and(DB::table('products')->where('id', $product->id)->value('sell_by_unit'))->toBe('0.500');
});
