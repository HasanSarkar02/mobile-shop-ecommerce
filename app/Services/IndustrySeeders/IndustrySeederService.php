<?php

declare(strict_types=1);

namespace App\Services\IndustrySeeders;

use App\Enums\TenantIndustry;
use App\Models\StoreThemeSetting;
use App\Models\Tenant;
use App\Models\UnitOfMeasure;
use App\Support\ThemePresets;

final class IndustrySeederService
{
    /**
     * Starter UOM catalogue per vertical (Phase C-1 #46). Grocery sells by
     * weight/volume; every tenant gets the discrete set. Values are data —
     * tenants can add their own later.
     */
    private const UOMS = [
        'grocery' => [
            ['code' => 'kg', 'name' => 'Kilogram', 'type' => 'weight'],
            ['code' => 'g', 'name' => 'Gram', 'type' => 'weight'],
            ['code' => 'l', 'name' => 'Litre', 'type' => 'volume'],
            ['code' => 'ml', 'name' => 'Millilitre', 'type' => 'volume'],
            ['code' => 'pcs', 'name' => 'Pieces', 'type' => 'discrete'],
            ['code' => 'pack', 'name' => 'Pack', 'type' => 'discrete'],
            ['code' => 'dozen', 'name' => 'Dozen', 'type' => 'discrete'],
        ],
        'general' => [
            ['code' => 'pcs', 'name' => 'Pieces', 'type' => 'discrete'],
            ['code' => 'pack', 'name' => 'Pack', 'type' => 'discrete'],
            ['code' => 'dozen', 'name' => 'Dozen', 'type' => 'discrete'],
        ],
    ];

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
        $this->seedUoms($tenant, $code);
    }

    /**
     * Starter UOM catalogue for the tenant (Phase C-1 #46) — idempotent
     * firstOrCreate keyed on (tenant_id, code); owner additions are never
     * touched.
     */
    private function seedUoms(Tenant $tenant, string $code): void
    {
        foreach (self::UOMS[$code] ?? self::UOMS['general'] as $uom) {
            UnitOfMeasure::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $uom['code']],
                ['name' => $uom['name'], 'type' => $uom['type']],
            );
        }
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
