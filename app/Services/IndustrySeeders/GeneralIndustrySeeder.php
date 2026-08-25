<?php

declare(strict_types=1);

namespace App\Services\IndustrySeeders;

use App\Enums\Visibility;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\StoreThemeSetting;
use App\Models\Tenant;
use App\Support\ThemePresets;

final class GeneralIndustrySeeder
{
    public function seed(Tenant $tenant): void
    {
        $this->firstOrCreateCategory($tenant, 'General');
        $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'Featured Products', 1);
        $this->seedTheme($tenant);
    }

    private function seedTheme(Tenant $tenant): void
    {
        // Preset: general → primary #16a34a, secondary #15803d (B-3)
        $preset = ThemePresets::forIndustry('general');

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

    private function firstOrCreateCategory(Tenant $tenant, string $name, ?int $parentId = null): Category
    {
        return Category::query()->firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => $name, 'parent_id' => $parentId],
            ['slug' => null]
        );
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
