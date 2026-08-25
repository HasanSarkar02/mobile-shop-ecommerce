<?php

declare(strict_types=1);

namespace App\Services\IndustrySeeders;

use App\Enums\TenantIndustry;
use App\Models\Tenant;

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
            TenantIndustry::Furniture->value => new FurnitureIndustrySeeder,
            TenantIndustry::Fashion->value => new FurnitureIndustrySeeder,
            TenantIndustry::Grocery->value => new GeneralIndustrySeeder,
            TenantIndustry::Sports->value => new GeneralIndustrySeeder,
            default => new GeneralIndustrySeeder,
        };

        $seeder->seed($tenant);
    }
}
