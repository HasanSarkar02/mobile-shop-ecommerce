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

final class SportsIndustrySeeder
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
        // Preset: sports → primary #dc2626, secondary #991b1b (B-3)
        $preset = ThemePresets::forIndustry('sports');

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
        $this->firstOrCreateCategory($tenant, 'Sports Equipment');
        $this->firstOrCreateCategory($tenant, 'Apparel');
        $this->firstOrCreateCategory($tenant, 'Footwear');
        $this->firstOrCreateCategory($tenant, 'Accessories');
    }

    private function seedAttributes(Tenant $tenant): void
    {
        $this->firstOrCreateAttribute($tenant, [
            'code' => 'size',
            'label' => 'Size',
            'data_type' => AttributeDataType::Select,
            'unit' => null,
            'group' => 'Specifications',
            'is_filterable' => true,
            'is_variant_defining' => true,
            'sort_order' => 1,
        ], ['S', 'M', 'L', 'XL', 'XXL']);

        $this->firstOrCreateAttribute($tenant, [
            'code' => 'sports_color',
            'label' => 'Color',
            'data_type' => AttributeDataType::Select,
            'unit' => null,
            'group' => 'Appearance',
            'is_filterable' => true,
            'is_variant_defining' => true,
            'sort_order' => 2,
        ], ['Black', 'White', 'Red', 'Blue', 'Green']);

        $this->firstOrCreateAttribute($tenant, [
            'code' => 'material',
            'label' => 'Material',
            'data_type' => AttributeDataType::Select,
            'unit' => null,
            'group' => 'Specifications',
            'is_filterable' => true,
            'is_variant_defining' => false,
            'sort_order' => 3,
        ], ['Polyester', 'Cotton', 'Nylon', 'Rubber']);
    }

    private function seedHomepage(Tenant $tenant): void
    {
        $this->firstOrCreateHomepageSection($tenant, 'category_grid', 'Shop by Sport', 1);
        $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'Top Picks', 2);
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
