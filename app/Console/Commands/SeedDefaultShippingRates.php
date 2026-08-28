<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BdDistrict;
use App\Models\Tenant;
use App\Models\TenantShippingRate;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Command;

class SeedDefaultShippingRates extends Command
{
    protected $signature = 'shipping:seed-defaults {--tenant= : Specific tenant ID}';

    protected $description = 'Seed default Inside Dhaka (80 BDT) and Outside Dhaka (120 BDT) rates for all tenants';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        $tenants = $tenantId ? Tenant::query()->whereKey($tenantId)->get() : Tenant::query()->get();

        if ($tenants->isEmpty()) {
            $this->error('No tenants found.');

            return self::FAILURE;
        }

        $dhakaDistrict = BdDistrict::query()->where('name_en', 'Dhaka')->first();

        if ($dhakaDistrict === null) {
            $this->error('Dhaka district not found. Run bd:seed first.');

            return self::FAILURE;
        }

        $count = 0;
        foreach ($tenants as $tenant) {
            app(Tenancy::class)->set($tenant);

            $inside = TenantShippingRate::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => 'Inside Dhaka', 'bd_district_id' => $dhakaDistrict->id],
                [
                    'bd_division_id' => $dhakaDistrict->division_id,
                    'bd_upazila_id' => null,
                    'charge' => 8000,
                    'free_threshold' => 100000,
                    'is_active' => true,
                    'sort_order' => 1,
                ]
            );

            $outside = TenantShippingRate::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => 'Outside Dhaka'],
                [
                    'bd_division_id' => null,
                    'bd_district_id' => null,
                    'bd_upazila_id' => null,
                    'charge' => 12000,
                    'free_threshold' => 100000,
                    'is_active' => true,
                    'sort_order' => 99,
                ]
            );
            // Ensure Outside is not accidentally district-specific
            if ($outside->bd_district_id !== null) {
                $outside->update(['bd_district_id' => null, 'bd_upazila_id' => null, 'bd_division_id' => null]);
            }

            $count += 2;
            $this->info("Tenant {$tenant->name} ({$tenant->id}): Inside Dhaka {$inside->charge}, Outside {$outside->charge}");
        }

        app(Tenancy::class)->set(null);

        $this->info("Seeded {$count} rates.");

        return self::SUCCESS;
    }
}
