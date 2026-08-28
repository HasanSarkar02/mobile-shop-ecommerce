<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\StockItem;
use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class CleanupOrphanStockItems extends Command
{
    protected $signature = 'stock:cleanup-orphans {--tenant= : Specific tenant ID} {--include-trashed : Also delete stock for soft-deleted variants} {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Cleanup orphaned stock_items where the product_variant is hard-deleted (or soft-deleted with --include-trashed)';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $includeTrashed = (bool) $this->option('include-trashed');
        $isDryRun = (bool) $this->option('dry-run');

        $tenants = $tenantId ? Tenant::query()->whereKey($tenantId)->get() : Tenant::query()->get();

        if ($tenants->isEmpty()) {
            $this->error('No tenants found.');

            return self::FAILURE;
        }

        $totalFound = 0;
        $totalDeleted = 0;

        foreach ($tenants as $tenant) {
            app(Tenancy::class)->set($tenant);

            $query = StockItem::query()->where(function (Builder $q) use ($includeTrashed): void {
                // Hard orphan: variant does not exist even with trashed
                $q->whereDoesntHave('variant', fn (Builder $vq) => $vq->withTrashed())
                    ->when($includeTrashed, function (Builder $q): void {
                        $q->orWhereHas('variant', fn (Builder $vq) => $vq->onlyTrashed());
                    });
            });

            $count = $query->count();
            $totalFound += $count;

            if ($count === 0) {
                $this->info("Tenant {$tenant->name} ({$tenant->id}): no orphan stock items found.");

                continue;
            }

            $this->info("Tenant {$tenant->name} ({$tenant->id}): found {$count} orphan stock item(s)".($includeTrashed ? ' (including soft-deleted variants)' : '').'.');

            if ($isDryRun) {
                $query->with(['variant' => fn ($q) => $q->withTrashed(), 'location'])->limit(20)->get()->each(function (StockItem $item): void {
                    $variantInfo = $item->variant ? $item->variant->sku.' (trashed: '.($item->variant->trashed() ? 'yes' : 'no').')' : 'null';
                    $this->line("  - StockItem #{$item->id}: variant {$variantInfo}, qty {$item->quantity}, location ".($item->location?->name ?? '—'));
                });

                if ($count > 20) {
                    $this->line('  ... and '.($count - 20).' more');
                }

                continue;
            }

            $deleted = $query->delete();
            $totalDeleted += $deleted;
            $this->info("  Deleted {$deleted} orphan stock item(s).");
        }

        app(Tenancy::class)->set(null);

        if ($isDryRun) {
            $this->info("Dry run complete. Found {$totalFound} orphan(s) — no deletions performed. Re-run without --dry-run to delete.");
        } else {
            $this->info("Done. Deleted {$totalDeleted} / {$totalFound} orphan stock item(s).");
        }

        return self::SUCCESS;
    }
}
