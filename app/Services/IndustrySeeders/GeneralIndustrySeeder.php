<?php

declare(strict_types=1);

namespace App\Services\IndustrySeeders;

use App\Enums\Visibility;
use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\Tenant;

final class GeneralIndustrySeeder
{
    public function seed(Tenant $tenant): void
    {
        $this->firstOrCreateCategory($tenant, 'General');
        $this->firstOrCreateHomepageSection($tenant, 'product_grid', 'Featured Products', 1);
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
