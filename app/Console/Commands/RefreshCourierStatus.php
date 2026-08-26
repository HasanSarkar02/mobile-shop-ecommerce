<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OrderFulfillmentStatus;
use App\Models\CourierConnection;
use App\Models\OrderFulfillment;
use App\Models\Tenant;
use App\Services\Shipping\CourierService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshCourierStatus extends Command
{
    protected $signature = 'tenants:refresh-courier-status';

    protected $description = 'Polls courier providers for open consignments across active tenants and syncs delivery statuses.';

    public function handle(CourierService $couriers): int
    {
        $synced = 0;
        $failed = 0;

        // Every live storefront (trial + active) can have open consignments;
        // only pending/rejected/suspended shops are excluded — mirrors Tenant::isActive().
        try {
            Tenant::query()
                ->whereIn('status', ['trial', 'active'])
                ->each(function (Tenant $tenant) use ($couriers, &$synced, &$failed): void {
                    app(Tenancy::class)->set($tenant);

                    // Open consignments only: still in flight and carrying a tracking code.
                    OrderFulfillment::query()
                        ->whereNotNull('tracking_number')
                        ->whereIn('status', [
                            OrderFulfillmentStatus::Pending,
                            OrderFulfillmentStatus::Packed,
                            OrderFulfillmentStatus::Shipped,
                        ])
                        ->chunkById(100, function ($fulfillments) use ($couriers, &$synced, &$failed): void {
                            foreach ($fulfillments as $fulfillment) {
                                $connection = $this->connectionFor($fulfillment);

                                if ($connection === null) {
                                    continue;
                                }

                                try {
                                    $couriers->syncStatus($fulfillment, $connection);
                                    $synced++;
                                } catch (Throwable $e) {
                                    // One provider outage / bad credential must never
                                    // abort the remaining consignments.
                                    $failed++;
                                    Log::error('Courier status sync failed for fulfillment '.$fulfillment->id.': '.$e->getMessage());
                                }
                            }
                        });
                });
        } finally {
            app(Tenancy::class)->set(null);
        }

        $this->info("Courier status sync complete: {$synced} synced, {$failed} failed.");

        return self::SUCCESS;
    }

    /**
     * Resolves the connection whose credentials created a consignment.
     *
     * The FK recorded at shipment time is authoritative, so it is tried first: it
     * survives a provider being renamed and cannot be confused by two providers
     * sharing a display name. Name matching remains only as the fallback for rows
     * shipped before the FK existed, or whose free-text courier_name the backfill
     * could not resolve unambiguously.
     *
     * Never guesses: zero matches is logged and skipped; ambiguous matches are
     * skipped loudly; a deactivated connection is skipped, because polling with
     * credentials the merchant has switched off is not something to do quietly.
     */
    private function connectionFor(OrderFulfillment $fulfillment): ?CourierConnection
    {
        if ($fulfillment->courier_connection_id) {
            $connection = CourierConnection::query()
                ->whereKey($fulfillment->courier_connection_id)
                ->first();

            if ($connection === null) {
                Log::info('Courier connection '.$fulfillment->courier_connection_id.' no longer exists for fulfillment '.$fulfillment->id.'; skipping.');

                return null;
            }

            if (! $connection->is_active) {
                Log::info('Courier connection '.$connection->id.' is inactive for fulfillment '.$fulfillment->id.'; skipping.');

                return null;
            }

            return $connection;
        }

        $name = (string) $fulfillment->courier_name;

        if ($name === '') {
            Log::info('No active courier connection found for fulfillment '.$fulfillment->id.' (no courier recorded).');

            return null;
        }

        $connections = CourierConnection::query()
            ->where('is_active', true)
            ->whereHas('provider', fn ($query) => $query
                ->where('name', $name)
                ->orWhere('display_name', $name))
            ->get();

        if ($connections->isEmpty()) {
            Log::info("No active courier connection found for '{$name}' (fulfillment {$fulfillment->id}).");

            return null;
        }

        if ($connections->count() > 1) {
            Log::warning("Ambiguous courier connection for '{$name}' (fulfillment {$fulfillment->id}); skipping.");

            return null;
        }

        return $connections->first();
    }
}
