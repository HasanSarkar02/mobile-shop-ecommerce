<?php

declare(strict_types=1);

namespace App\Services\IndustrySeeders;

use App\Enums\AttributeDataType;
use App\Enums\Visibility;
use App\Models\AttributeDefinition;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\StoreThemeSetting;
use App\Models\Tenant;
use App\Support\ThemePresets;

final class GroceryIndustrySeeder
{
    public function seed(Tenant $tenant): void
    {
        $this->seedCategories($tenant);
        $this->seedAttributes($tenant);
        $this->seedHomepage($tenant);
        $this->seedTheme($tenant);
    }

    private function seedTheme(Tenant $tenant): void
    {
        // Preset: grocery → primary #22c55e, secondary #15803d (B-3)
        $preset = ThemePresets::forIndustry('grocery');

        $existing = StoreThemeSetting::query()->where('tenant_id', $tenant->id)->first();

        if ($existing === null) {
            StoreThemeSetting::query()->create([
                'tenant_id' => $tenant->id,
                'primary_color' => $preset['primary'],
                'secondary_color' => $preset['secondary'],
            ]);

            return;
        }

        $updates = [];

        $primaryRaw = $existing->getAttribute('primary_color');
        if ($primaryRaw === null || $primaryRaw === '') {
            $updates['primary_color'] = $preset['primary'];
        }

        $secondaryRaw = $existing->getAttribute('secondary_color');
        if ($secondaryRaw === null || $secondaryRaw === '') {
            $updates['secondary_color'] = $preset['secondary'];
        }

        if ($updates !== []) {
            $existing->update($updates);
        }
    }

    private function seedCategories(Tenant $tenant): void
    {
        $this->firstOrCreateCategory($tenant, 'Fresh Produce');
        $this->firstOrCreateCategory($tenant, 'Pantry Staples');
        $this->firstOrCreateCategory($tenant, 'Dairy & Eggs');
        $this->firstOrCreateCategory($tenant, 'Beverages');
    }

    private function seedAttributes(Tenant $tenant): void
    {
        $this->firstOrCreateAttribute($tenant, [
            'code' => 'weight',
            'label' => 'Weight',
            'data_type' => AttributeDataType::Decimal,
            'unit' => 'kg',
            'group' => 'Specifications',
            'is_filterable' => true,
            'is_variant_defining' => true,
            'sort_order' => 1,
        ], []);

        $this->firstOrCreateAttribute($tenant, [
            'code' => 'unit',
            'label' => 'Unit',
            'data_type' => AttributeDataType::Select,
            'unit' => null,
            'group' => 'Specifications',
            'is_filterable' => true,
            'is_variant_defining' => true,
            'sort_order' => 2,
        ], ['kg', 'g', 'L', 'ml', 'pcs', 'pack']);

        $this->firstOrCreateAttribute($tenant, [
            'code' => 'pack_size',
            'label' => 'Pack Size',
            'data_type' => AttributeDataType::Select,
            'unit' => null,
            'group' => 'Specifications',
            'is_filterable' => true,
            'is_variant_defining' => true,
            'sort_order' => 3,
        ], ['Single', 'Pack of 2', 'Pack of 5', 'Family Pack']);
    }

    private function seedHomepage(Tenant $tenant): void
    {
        $this->firstOrCreateHomepageSection($tenant, 'category_grid', 'Shop by Category', 1);
        $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'Fresh Arrivals', 2);
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
