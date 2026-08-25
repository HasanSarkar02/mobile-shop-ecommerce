<?php

declare(strict_types=1);

namespace App\Services\IndustrySeeders;

use App\Enums\AttributeDataType;
use App\Enums\Visibility;
use App\Models\AttributeDefinition;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\Tenant;

final class ElectronicsIndustrySeeder
{
    public function seed(Tenant $tenant): void
    {
        $this->seedCategories($tenant);
        $this->seedAttributes($tenant);
        $this->seedHomepage($tenant);
    }

    private function seedCategories(Tenant $tenant): void
    {
        $smartphones = $this->firstOrCreateCategory($tenant, 'Smartphones');
        $this->firstOrCreateCategory($tenant, 'Accessories', $smartphones->id);
        $this->firstOrCreateCategory($tenant, 'Laptops');
    }

    private function seedAttributes(Tenant $tenant): void
    {
        $this->firstOrCreateAttribute($tenant, [
            'code' => 'storage_capacity',
            'label' => 'Storage Capacity',
            'data_type' => AttributeDataType::Select,
            'unit' => 'GB',
            'group' => 'Specifications',
            'is_filterable' => true,
            'is_variant_defining' => true,
            'sort_order' => 1,
        ], ['64GB', '128GB', '256GB', '512GB']);

        $this->firstOrCreateAttribute($tenant, [
            'code' => 'ram',
            'label' => 'RAM',
            'data_type' => AttributeDataType::Select,
            'unit' => 'GB',
            'group' => 'Specifications',
            'is_filterable' => true,
            'is_variant_defining' => true,
            'sort_order' => 2,
        ], ['4GB', '8GB', '12GB', '16GB']);

        $this->firstOrCreateAttribute($tenant, [
            'code' => 'device_color',
            'label' => 'Device Color',
            'data_type' => AttributeDataType::Select,
            'unit' => null,
            'group' => 'Appearance',
            'is_filterable' => true,
            'is_variant_defining' => true,
            'sort_order' => 3,
        ], ['Black', 'White', 'Blue', 'Titanium']);
    }

    private function seedHomepage(Tenant $tenant): void
    {
        // Spec-led for electronics: category grid + product grid (spec-led)
        $this->firstOrCreateHomepageSection($tenant, 'category_grid', 'Shop by Category', 1);
        $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'Featured Products', 2);
    }

    private function firstOrCreateCategory(Tenant $tenant, string $name, ?int $parentId = null): Category
    {
        return Category::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => $name, 'parent_id' => $parentId],
            ['slug' => null]
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $options
     */
    private function firstOrCreateAttribute(Tenant $tenant, array $attributes, array $options): AttributeDefinition
    {
        $definition = AttributeDefinition::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => $attributes['code']],
            [
                'label' => $attributes['label'],
                'data_type' => $attributes['data_type'],
                'unit' => $attributes['unit'],
                'group' => $attributes['group'],
                'is_filterable' => $attributes['is_filterable'],
                'is_variant_defining' => $attributes['is_variant_defining'],
                'sort_order' => $attributes['sort_order'],
                'is_global' => false,
            ]
        );

        foreach ($options as $idx => $value) {
            AttributeOption::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'attribute_definition_id' => $definition->id, 'value' => $value],
                ['label' => $value, 'sort_order' => $idx + 1]
            );
        }

        return $definition;
    }

    private function firstOrCreateHomepageSection(Tenant $tenant, string $type, string $title, int $sortOrder): HomepageSection
    {
        return HomepageSection::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'type' => $type, 'title' => $title],
            [
                'config' => [],
                'visibility' => Visibility::All,
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]
        );
    }
}
