<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces string matching with a real foreign key for consignment -> credential
 * resolution.
 *
 * Before this migration the hourly poller had to resolve which CourierConnection
 * a consignment belonged to by matching the free-text `courier_name` against
 * `courier_providers.name`/`display_name`. That is fragile: a renamed provider,
 * a hand-typed courier name, or two providers sharing a display name all break
 * it silently.
 *
 * The backfill deliberately reproduces the *exact* rule the poller already used,
 * so it can only ever assign a connection the poller would have chosen anyway:
 * an active connection belonging to the same tenant, whose provider's name or
 * display name equals the recorded `courier_name`, and only when that match is
 * unambiguous.
 *
 * Rows whose name matched nothing, or matched more than one connection, are left
 * NULL ON PURPOSE. A NULL here means "the string never resolved" and is the
 * signal that the name-matching fallback must still be used for that row — it is
 * not missing data to be filled in later.
 *
 * Eloquent is unusable here: tenant-scoped models carry the fail-closed
 * BelongsToTenant global scope and there is no tenant context inside a
 * migration, so every model query would return an empty set. The query builder
 * is used throughout for that reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_fulfillments', function (Blueprint $table): void {
            $table->foreignId('courier_connection_id')
                ->nullable()
                ->after('courier_name')
                ->constrained('courier_connections')
                ->nullOnDelete();

            $table->index(['tenant_id', 'courier_connection_id']);
        });

        $this->backfill();
    }

    /**
     * Public, and separate from up(), so the rule can be regression-tested
     * directly. A test that re-declared this query would be testing a copy of it;
     * calling it is the only way to prove the migration itself resolves names the
     * way the poller does.
     *
     * @return int the number of fulfillments that were resolved
     */
    public function backfill(): int
    {
        // Unambiguous groups only: COUNT(DISTINCT c.id) = 1 means every active
        // connection this (tenant, courier_name) pair could refer to is the same
        // one, so assigning it cannot change which credentials get used.
        $groups = DB::table('order_fulfillments as f')
            ->join('courier_connections as c', 'c.tenant_id', '=', 'f.tenant_id')
            ->join('courier_providers as p', 'p.id', '=', 'c.courier_provider_id')
            ->whereNull('f.courier_connection_id')
            ->whereNotNull('f.courier_name')
            ->where('f.courier_name', '!=', '')
            ->where('c.is_active', true)
            ->where(function ($query): void {
                $query->whereColumn('p.name', 'f.courier_name')
                    ->orWhereColumn('p.display_name', 'f.courier_name');
            })
            ->groupBy('f.tenant_id', 'f.courier_name')
            ->havingRaw('COUNT(DISTINCT c.id) = 1')
            ->select([
                'f.tenant_id',
                'f.courier_name',
                DB::raw('MIN(c.id) as courier_connection_id'),
            ])
            ->get();

        $resolved = 0;

        foreach ($groups as $group) {
            $resolved += DB::table('order_fulfillments')
                ->where('tenant_id', $group->tenant_id)
                ->where('courier_name', $group->courier_name)
                ->whereNull('courier_connection_id')
                ->update(['courier_connection_id' => $group->courier_connection_id]);
        }

        return $resolved;
    }

    public function down(): void
    {
        Schema::table('order_fulfillments', function (Blueprint $table): void {
            $table->dropForeign(['courier_connection_id']);
            $table->dropIndex(['tenant_id', 'courier_connection_id']);
            $table->dropColumn('courier_connection_id');
        });
    }
};
