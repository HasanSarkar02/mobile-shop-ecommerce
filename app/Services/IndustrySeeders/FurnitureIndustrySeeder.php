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

final class FurnitureIndustrySeeder
{
    public function seed(Tenant $tenant): void
    {
        $this->seedCategories($tenant);
        $this->seedAttributes($tenant);
        $this->seedHomepage($tenant);
    }

    private function seedCategories(Tenant $tenant): void
    {
        $this->firstOrCreateCategory($tenant, 'Living Room');
        $this->firstOrCreateCategory($tenant, 'Bedroom');
        $this->firstOrCreateCategory($tenant, 'Office');
    }

    private function seedAttributes(Tenant $tenant): void
    {
        $this->firstOrCreateAttribute($tenant, [
            'code' => 'material',
            'label' => 'Material',
            'data_type' => AttributeDataType::Select,
            'unit' => null,
            'group' => 'Specifications',
            'is_filterable' => true,
            'is_variant_defining' => true,
            'sort_order' => 1,
        ], ['Wood', 'Metal', 'Fabric', 'Leather']);

        $this->firstOrCreateAttribute($tenant, [
            'code' => 'dimensions',
            'label' => 'Dimensions',
            'data_type' => AttributeDataType::Text,
            'unit' => 'cm',
            'group' => 'Specifications',
            'is_filterable' => false,
            'is_variant_defining' => true,
            'sort_order' => 2,
        ], []);

        $this->firstOrCreateAttribute($tenant, [
            'code' => 'upholstery_color',
            'label' => 'Upholstery Color',
            'data_type' => AttributeDataType::Select,
            'unit' => null,
            'group' => 'Appearance',
            'is_filterable' => true,
            'is_variant_defining' => true,
            'sort_order' => 3,
        ], ['Beige', 'Gray', 'Brown', 'Navy']);
    }

    private function seedHomepage(Tenant $tenant): void
    {
        // Imagery-heavy for furniture: large category grid + banner
        $this->firstOrCreateHomepageSection($tenant, 'category_grid', 'Browse by Room', 1);
        $this->firstOrCreateHomepageSection($tenant, 'banner_carousel', 'Featured Collections', 2);
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
