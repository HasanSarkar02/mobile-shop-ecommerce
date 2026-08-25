<?php

declare(strict_types=1);

namespace App\Services\IndustrySeeders;

use App\Enums\TenantIndustry;
use App\Models\StoreThemeSetting;
use App\Models\Tenant;
use App\Support\ThemePresets;

final class IndustrySeederService
{
    public function seed(Tenant $tenant): void
    {
        /** @var mixed $industry */
        $industry = $tenant->industry;

        if ($industry instanceof TenantIndustry) {
            $code = $industry->value;
        } elseif (is_string($industry) && $industry !== '') {
            $code = strtolower($industry);
        } else {
            $code = 'general';
        }

        $seeder = match ($code) {
            TenantIndustry::Electronics->value => new ElectronicsIndustrySeeder,
            TenantIndustry::Mobile->value => new ElectronicsIndustrySeeder,
            TenantIndustry::Fashion->value => new FashionIndustrySeeder,
            TenantIndustry::Grocery->value => new GroceryIndustrySeeder,
            TenantIndustry::Sports->value => new SportsIndustrySeeder,
            TenantIndustry::Furniture->value => new FurnitureIndustrySeeder,
            default => new GeneralIndustrySeeder,
        };

        $seeder->seed($tenant);

        $this->ensureThemeSettings($tenant, $code);
    }

    private function ensureThemeSettings(Tenant $tenant, string $code): void
    {
        $preset = ThemePresets::forIndustry($code);

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
}
