<?php

declare(strict_types=1);

use App\Enums\OrderFulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\CourierConnection;
use App\Models\CourierProvider;
use App\Models\Order;
use App\Models\OrderFulfillment;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->tenant = actingAsTenant(['subdomain' => 'backfill-'.Str::lower(Str::random(6)), 'status' => 'active']);
});

/**
 * The migration object itself, so the assertions below run the exact query that
 * shipped. Re-declaring the query in the test would only prove the copy works.
 */
function backfillMigration(): object
{
    return require database_path('migrations/2026_08_23_000001_add_courier_connection_id_to_order_fulfillments_table.php');
}

function backfillProvider(string $code, string $name, ?string $displayName = null): CourierProvider
{
    return CourierProvider::query()->create([
        'code' => $code,
        'name' => $name,
        'display_name' => $displayName,
        'is_active' => true,
    ]);
}

function backfillConnection(CourierProvider $provider, bool $active = true, ?int $tenantId = null): CourierConnection
{
    return CourierConnection::query()->create([
        'tenant_id' => $tenantId ?? tenant()->id,
        'courier_provider_id' => $provider->id,
        'is_active' => $active,
        'credentials' => [],
    ]);
}

function backfillFulfillment(?string $courierName, ?int $tenantId = null): OrderFulfillment
{
    $tenantId ??= tenant()->id;

    $order = Order::query()->create([
        'tenant_id' => $tenantId,
        'order_number' => 'ORD-BF-'.Str::random(8),
        'status' => OrderStatus::Shipped,
    ]);

    return $order->fulfillments()->create([
        'tenant_id' => $tenantId,
        'status' => OrderFulfillmentStatus::Shipped,
        'fulfillment_group' => 'stock',
        'tracking_number' => 'TRACK-'.Str::random(6),
        'courier_name' => $courierName,
    ]);
}

/** Simulates the pre-migration state for rows created after it ran. */
function backfillClearFk(OrderFulfillment ...$fulfillments): void
{
    DB::table('order_fulfillments')
        ->whereIn('id', array_map(fn (OrderFulfillment $f): int => $f->id, $fulfillments))
        ->update(['courier_connection_id' => null]);
}

function backfilledConnectionId(OrderFulfillment $fulfillment): ?int
{
    $value = DB::table('order_fulfillments')->where('id', $fulfillment->id)->value('courier_connection_id');

    return $value === null ? null : (int) $value;
}

it('resolves a consignment whose courier name matches exactly one connection', function (): void {
    $provider = backfillProvider('bf_steadfast', 'Steadfast');
    $connection = backfillConnection($provider);
    $fulfillment = backfillFulfillment('Steadfast');
    backfillClearFk($fulfillment);

    backfillMigration()->backfill();

    expect(backfilledConnectionId($fulfillment))->toBe($connection->id);
});

it('matches on the provider display name as well as its name', function (): void {
    // The poller accepts either, because the admin form has historically written
    // whichever label was on screen.
    $provider = backfillProvider('bf_pathao', 'pathao', 'Pathao Courier');
    $connection = backfillConnection($provider);
    $fulfillment = backfillFulfillment('Pathao Courier');
    backfillClearFk($fulfillment);

    backfillMigration()->backfill();

    expect(backfilledConnectionId($fulfillment))->toBe($connection->id);
});

it('leaves an ambiguous name NULL instead of guessing', function (): void {
    // Two providers sharing a label means the string cannot identify credentials.
    // Guessing here would send a consignment to the wrong courier account.
    $connection = backfillConnection(backfillProvider('bf_a', 'Shared Label'));
    backfillConnection(backfillProvider('bf_b', 'Shared Label'));

    $fulfillment = backfillFulfillment('Shared Label');
    backfillClearFk($fulfillment);

    backfillMigration()->backfill();

    expect(backfilledConnectionId($fulfillment))->toBeNull();
    expect(backfilledConnectionId($fulfillment))->not->toBe($connection->id);
});

it('leaves a name that matches nothing NULL', function (): void {
    // A NULL is the signal that the name never resolved, which is what keeps the
    // poller on its name-matching fallback for that row.
    backfillConnection(backfillProvider('bf_known', 'Known Courier'));
    $fulfillment = backfillFulfillment('Hand Typed Courier');
    backfillClearFk($fulfillment);

    backfillMigration()->backfill();

    expect(backfilledConnectionId($fulfillment))->toBeNull();
});

it('ignores an inactive connection', function (): void {
    // Matching today's poller semantics: an inactive connection is not usable, so
    // resolving to it would only fail later with worse diagnostics.
    backfillConnection(backfillProvider('bf_off', 'Retired Courier'), active: false);
    $fulfillment = backfillFulfillment('Retired Courier');
    backfillClearFk($fulfillment);

    backfillMigration()->backfill();

    expect(backfilledConnectionId($fulfillment))->toBeNull();
});

it('leaves a consignment with no courier name NULL', function (): void {
    backfillConnection(backfillProvider('bf_any', 'Any Courier'));
    $fulfillment = backfillFulfillment(null);
    backfillClearFk($fulfillment);

    backfillMigration()->backfill();

    expect(backfilledConnectionId($fulfillment))->toBeNull();
});

it('never resolves across tenants', function (): void {
    // The name is identical in both tenants; only the owning tenant's connection
    // may ever be used.
    $provider = backfillProvider('bf_cross', 'Cross Courier');
    $ours = backfillConnection($provider);

    $other = actingAsTenant(['subdomain' => 'backfill-other-'.Str::lower(Str::random(6)), 'status' => 'active']);
    $theirs = backfillConnection($provider, tenantId: $other->id);
    $theirFulfillment = backfillFulfillment('Cross Courier', tenantId: $other->id);

    app(Tenancy::class)->set($this->tenant);
    $ourFulfillment = backfillFulfillment('Cross Courier');

    backfillClearFk($ourFulfillment, $theirFulfillment);

    backfillMigration()->backfill();

    expect(backfilledConnectionId($ourFulfillment))->toBe($ours->id);
    expect(backfilledConnectionId($theirFulfillment))->toBe($theirs->id);
});

it('does not overwrite a connection that was already recorded', function (): void {
    // sendFulfillment() writes the FK directly. The backfill is only ever allowed
    // to fill a gap, never to correct a real record.
    $recorded = backfillConnection(backfillProvider('bf_real', 'Real Courier'));
    $other = backfillConnection(backfillProvider('bf_other', 'Other Courier'));

    $fulfillment = backfillFulfillment('Other Courier');
    DB::table('order_fulfillments')->where('id', $fulfillment->id)
        ->update(['courier_connection_id' => $recorded->id]);

    backfillMigration()->backfill();

    expect(backfilledConnectionId($fulfillment))->toBe($recorded->id);
    expect(backfilledConnectionId($fulfillment))->not->toBe($other->id);
});

it('is safe to run twice', function (): void {
    $connection = backfillConnection(backfillProvider('bf_twice', 'Twice Courier'));
    $fulfillment = backfillFulfillment('Twice Courier');
    backfillClearFk($fulfillment);

    $first = backfillMigration()->backfill();
    $second = backfillMigration()->backfill();

    expect($first)->toBe(1);
    expect($second)->toBe(0);
    expect(backfilledConnectionId($fulfillment))->toBe($connection->id);
});

it('blanks the foreign key when a connection is deleted, keeping order history', function (): void {
    // nullOnDelete, never cascade: deleting a courier account must never delete
    // the consignment record it shipped.
    $connection = backfillConnection(backfillProvider('bf_del', 'Deleted Courier'));
    $fulfillment = backfillFulfillment('Deleted Courier');

    DB::table('order_fulfillments')->where('id', $fulfillment->id)
        ->update(['courier_connection_id' => $connection->id]);

    $connection->delete();

    $row = DB::table('order_fulfillments')->where('id', $fulfillment->id)->first();

    expect($row)->not->toBeNull();
    expect($row->courier_connection_id)->toBeNull();
    // The historical label survives — it is what the public tracking page renders.
    expect($row->courier_name)->toBe('Deleted Courier');
    expect($row->tracking_number)->toBe($fulfillment->tracking_number);
});
