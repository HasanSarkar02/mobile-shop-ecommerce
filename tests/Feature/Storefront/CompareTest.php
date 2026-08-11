<?php

declare(strict_types=1);

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductTranslation;
use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;
use Database\Factories\AttributeDefinitionFactory;

function compareBaseUrl(Tenant $tenant): string
{
    return 'http://'.$tenant->subdomain.'.'.config('tenancy.central_domain');
}

beforeEach(function () {
    $this->tenant = actingAsTenant(['subdomain' => 'demo', 'status' => 'active']);
    $this->base = compareBaseUrl($this->tenant);
});

it('renders an empty state when nothing is compared', function () {
    app(Tenancy::class)->set(null);

    $this->withSession(['compare_product_ids' => []])
        ->get($this->base.'/compare')
        ->assertOk()
        ->assertSee('Your comparison list is empty');
});

it('adds a product to the comparison via the toggle endpoint', function () {
    $variant = createTestVariant();
    app(Tenancy::class)->set(null);

    $this->postJson($this->base.'/compare/toggle', ['product_id' => $variant->product_id])
        ->assertOk()
        ->assertJson(['added' => true]);

    expect(session('compare_product_ids'))->toBe([$variant->product_id]);
});

it('removes a product from the comparison via the toggle endpoint', function () {
    $variant = createTestVariant();
    $id = $variant->product_id;
    app(Tenancy::class)->set(null);

    $this->withSession(['compare_product_ids' => [$id]])
        ->postJson($this->base.'/compare/toggle', ['product_id' => $id])
        ->assertOk()
        ->assertJson(['added' => false]);

    expect(session('compare_product_ids'))->toBe([]);
});

it('removes a single product without clearing the rest', function () {
    $a = createTestVariant();
    $b = createTestVariant();
    $ids = [$a->product_id, $b->product_id];
    app(Tenancy::class)->set(null);

    $this->withSession(['compare_product_ids' => $ids])
        ->postJson($this->base.'/compare/remove', ['product_id' => $b->product_id])
        ->assertOk();

    expect(session('compare_product_ids'))->toBe([$a->product_id]);
});

it('clears the whole comparison list', function () {
    $variant = createTestVariant();
    app(Tenancy::class)->set(null);

    $this->withSession(['compare_product_ids' => [$variant->product_id]])
        ->postJson($this->base.'/compare/clear')
        ->assertOk();

    expect(session('compare_product_ids'))->toBeNull();
});

it('enforces the maximum of four compared products', function () {
    $ids = [];
    for ($i = 0; $i < 4; $i++) {
        $ids[] = createTestVariant()->product_id;
    }
    $fifth = createTestVariant();
    app(Tenancy::class)->set(null);

    $this->withSession(['compare_product_ids' => $ids])
        ->postJson($this->base.'/compare/toggle', ['product_id' => $fifth->product_id])
        ->assertStatus(422)
        ->assertJson(['added' => false]);

    expect(session('compare_product_ids'))->toBe($ids);
});

it('renders compared products with their dynamic specifications', function () {
    $variant = createTestVariant();
    $product = $variant->product;
    $definition = AttributeDefinitionFactory::new()->create(['label' => 'RAM', 'code' => 'ram', 'sort_order' => 1]);
    ProductAttributeValue::query()->create([
        'product_id' => $product->id,
        'attribute_definition_id' => $definition->id,
        'value_string' => '8 GB',
    ]);
    app(Tenancy::class)->set(null);

    $this->withSession(['compare_product_ids' => [$product->id]])
        ->get($this->base.'/compare')
        ->assertOk()
        ->assertSee('8 GB')
        ->assertSee('RAM');
});

it('prunes unpublished products from the comparison', function () {
    $published = createTestVariant();
    $draft = Product::factory()->create(['status' => ProductStatus::Draft]);
    ProductTranslation::factory()->for($draft)->create(['locale' => 'en']);
    app(Tenancy::class)->set(null);

    $this->withSession(['compare_product_ids' => [$published->product_id, $draft->id]])
        ->get($this->base.'/compare')
        ->assertOk()
        ->assertDontSee($draft->translation('en')->name);

    expect(session('compare_product_ids'))->toBe([$published->product_id]);
});

it('never exposes products owned by another tenant', function () {
    $own = createTestVariant();

    $otherTenant = Tenant::factory()->create(['status' => 'active']);
    $foreign = Product::factory()->create([
        'status' => ProductStatus::Published,
        'tenant_id' => $otherTenant->id,
    ]);
    ProductTranslation::factory()->for($foreign)->create(['locale' => 'en']);

    app(Tenancy::class)->set(null);

    $this->withSession(['compare_product_ids' => [$own->product_id, $foreign->id]])
        ->get($this->base.'/compare')
        ->assertOk()
        ->assertDontSee($foreign->translation('en')->name);

    expect(session('compare_product_ids'))->toBe([$own->product_id]);
});
