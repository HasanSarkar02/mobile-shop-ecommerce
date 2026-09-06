<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Visibility;
use App\Models\HomepageSection;
use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Command;

class BackfillTrustBadges extends Command
{
    protected $signature = 'tenants:backfill-trust-badges';

    protected $description = 'Idempotent backfill: creates missing trust_badges HomepageSection for existing tenants (config=[], is_active=true).';

    public function handle(): int
    {
        $tenants = Tenant::query()->get();
        $created = 0;
        $skipped = 0;

        foreach ($tenants as $tenant) {
            app(Tenancy::class)->set($tenant);

            $exists = HomepageSection::query()
                ->where('type', 'trust_badges')
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            // Sensible homepage position: Hero (banner_carousel 2) → Trust → Featured.
            // furniture has banner_carousel 2, so trust 3; others trust 2 after category_grid 1.
            $sortOrder = 2;

            if ($tenant->industryCode() === 'furniture') {
                $sortOrder = 3;
            }

            HomepageSection::query()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'type' => 'trust_badges', 'title' => 'Our Promise'],
                [
                    'config' => [],
                    'visibility' => Visibility::All,
                    'is_active' => true,
                    'sort_order' => $sortOrder,
                ]
            );

            $created++;
        }

        app(Tenancy::class)->set(null);

        $this->info("Trust badges backfill complete: {$created} created, {$skipped} already existed.");

        return self::SUCCESS;
    }
}
