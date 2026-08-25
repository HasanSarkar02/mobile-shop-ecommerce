<?php

declare(strict_types=1);

use App\Enums\TenantIndustry;
use App\Models\AttributeDefinition;
use App\Models\Category;
use App\Models\HomepageSection;

it('seeds electronics categories and attributes for electronics tenant', function (): void {
    $tenant = actingAsTenant(['industry' => TenantIndustry::Electronics->value]);

    $categories = Category::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->pluck('name')->all();
    expect($categories)->toContain('Smartphones')
        ->toContain('Accessories')
        ->toContain('Laptops');

    $codes = AttributeDefinition::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->pluck('code')->all();
    expect($codes)->toContain('storage_capacity')
        ->toContain('ram')
        ->toContain('device_color');

    $storage = AttributeDefinition::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('code', 'storage_capacity')->firstOrFail();
    expect($storage->is_variant_defining)->toBeTrue();
    expect($storage->options()->pluck('value')->all())->toContain('128GB');

    $sections = HomepageSection::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->get()->pluck('type')->map(fn ($t) => $t instanceof BackedEnum ? $t->value : (string) $t)->all();
    expect($sections)->toContain('category_grid');
});

it('seeds furniture categories and attributes for furniture tenant', function (): void {
    $tenant = actingAsTenant(['industry' => TenantIndustry::Furniture->value]);

    $categories = Category::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->pluck('name')->all();
    expect($categories)->toContain('Living Room')
        ->toContain('Bedroom')
        ->toContain('Office');

    $codes = AttributeDefinition::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->pluck('code')->all();
    expect($codes)->toContain('material')
        ->toContain('dimensions')
        ->toContain('upholstery_color');

    $material = AttributeDefinition::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->where('code', 'material')->firstOrFail();
    expect($material->is_variant_defining)->toBeTrue();

    $sections = HomepageSection::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->get()->pluck('type')->map(fn ($t) => $t instanceof BackedEnum ? $t->value : (string) $t)->all();
    expect($sections)->toContain('category_grid');
});

it('seeds general fallback for unknown industry', function (): void {
    $tenant = actingAsTenant(['industry' => TenantIndustry::General->value]);

    $categories = Category::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->pluck('name')->all();
    expect($categories)->toContain('General');

    $sections = HomepageSection::query()->withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->get()->pluck('type')->map(fn ($t) => $t instanceof BackedEnum ? $t->value : (string) $t)->all();
    expect($sections)->toContain('product_grid');
});

it('isolates seeded data between tenants', function (): void {
    $electronics = actingAsTenant(['industry' => TenantIndustry::Electronics->value, 'subdomain' => 'elec-'.uniqid()]);
    $furniture = actingAsTenant(['industry' => TenantIndustry::Furniture->value, 'subdomain' => 'furn-'.uniqid()]);

    $elecCats = Category::query()->withoutGlobalScope('tenant')->where('tenant_id', $electronics->id)->pluck('name')->all();
    $furnCats = Category::query()->withoutGlobalScope('tenant')->where('tenant_id', $furniture->id)->pluck('name')->all();

    expect($elecCats)->toContain('Smartphones')->not->toContain('Living Room');
    expect($furnCats)->toContain('Living Room')->not->toContain('Smartphones');

    $elecAttrs = AttributeDefinition::query()->withoutGlobalScope('tenant')->where('tenant_id', $electronics->id)->pluck('code')->all();
    $furnAttrs = AttributeDefinition::query()->withoutGlobalScope('tenant')->where('tenant_id', $furniture->id)->pluck('code')->all();

    expect($elecAttrs)->toContain('storage_capacity')->not->toContain('material');
    expect($furnAttrs)->toContain('material')->not->toContain('storage_capacity');
});
