<?php

declare(strict_types=1);

use App\Console\Commands\RefreshCourierStatus;
use App\Enums\OrderFulfillmentStatus;
use App\Models\CourierConnection;
use App\Models\CourierProvider;
use App\Models\Order;
use App\Models\OrderFulfillment;
use App\Services\Shipping\CourierService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function courierTestOrder(object $tenant, string $number): int
{
    return Order::query()->create([
        'tenant_id' => $tenant->id,
        'order_number' => $number,
        'status' => 'confirmed',
    ])->id;
}

beforeEach(function () {
    $this->tenant = actingAsTenant(['subdomain' => 'courier-test', 'status' => 'active']);
    app(Tenancy::class)->set($this->tenant);

    $this->provider = CourierProvider::query()->create([
        'code' => 'test_courier',
        'name' => 'Test Courier',
        'is_active' => true,
    ]);
});

it('syncs eligible fulfillment', function () {
    CourierConnection::query()->create([
        'tenant_id' => $this->tenant->id,
        'courier_provider_id' => $this->provider->id,
        'is_active' => true,
        'credentials' => [],
    ]);

    $fulfillment = OrderFulfillment::query()->create([
        'tenant_id' => $this->tenant->id,
        'order_id' => courierTestOrder($this->tenant, 'ORD-TEST-'.Str::random(6)),
        'status' => OrderFulfillmentStatus::Shipped->value,
        'tracking_number' => 'TRACK123',
        'courier_name' => 'Test Courier',
    ]);

    $mock = Mockery::mock(CourierService::class);
    $mock->shouldReceive('syncStatus')->once()->with(
        Mockery::on(fn ($f) => $f->id === $fulfillment->id),
        Mockery::on(fn ($c) => $c->tenant_id === $this->tenant->id)
    );
    app()->instance(CourierService::class, $mock);

    $this->artisan(RefreshCourierStatus::class)
        ->assertSuccessful();
});

it('skips missing connection safely', function () {
    $fulfillment = OrderFulfillment::query()->create([
        'tenant_id' => $this->tenant->id,
        'order_id' => courierTestOrder($this->tenant, 'ORD-TEST-'.Str::random(6)),
        'status' => OrderFulfillmentStatus::Shipped->value,
        'tracking_number' => 'TRACK123',
        'courier_name' => 'Missing Courier',
    ]);

    $mock = Mockery::mock(CourierService::class);
    $mock->shouldReceive('syncStatus')->never();
    app()->instance(CourierService::class, $mock);

    Log::shouldReceive('info')->withArgs(function ($msg) {
        return str_contains($msg, 'No active courier connection found');
    });

    $this->artisan(RefreshCourierStatus::class)
        ->assertSuccessful();
});

it('does not guess ambiguous connections', function () {
    // Two DIFFERENT providers sharing the same display name: the command must
    // refuse to guess which connection a consignment belongs to.
    CourierProvider::query()->create([
        'code' => 'test_courier_alt',
        'name' => 'Test Courier',
        'is_active' => true,
    ]);

    CourierConnection::query()->create([
        'tenant_id' => $this->tenant->id,
        'courier_provider_id' => $this->provider->id,
        'is_active' => true,
        'credentials' => [],
    ]);
    CourierConnection::query()->create([
        'tenant_id' => $this->tenant->id,
        'courier_provider_id' => CourierProvider::query()->where('code', 'test_courier_alt')->firstOrFail()->id,
        'is_active' => true,
        'credentials' => [],
    ]);

    $fulfillment = OrderFulfillment::query()->create([
        'tenant_id' => $this->tenant->id,
        'order_id' => courierTestOrder($this->tenant, 'ORD-TEST-'.Str::random(6)),
        'status' => OrderFulfillmentStatus::Shipped->value,
        'tracking_number' => 'TRACK123',
        'courier_name' => 'Test Courier',
    ]);

    $mock = Mockery::mock(CourierService::class);
    $mock->shouldReceive('syncStatus')->never();
    app()->instance(CourierService::class, $mock);

    Log::shouldReceive('warning')->withArgs(function ($msg) {
        return str_contains($msg, 'Ambiguous courier connection');
    });

    $this->artisan(RefreshCourierStatus::class)
        ->assertSuccessful();
});

it('ignores ineligible fulfillment', function () {
    CourierConnection::query()->create([
        'tenant_id' => $this->tenant->id,
        'courier_provider_id' => $this->provider->id,
        'is_active' => true,
        'credentials' => [],
    ]);

    $fulfillment = OrderFulfillment::query()->create([
        'tenant_id' => $this->tenant->id,
        'order_id' => courierTestOrder($this->tenant, 'ORD-TEST-'.Str::random(6)),
        'status' => OrderFulfillmentStatus::Delivered->value,
        'tracking_number' => 'TRACK123',
        'courier_name' => 'Test Courier',
    ]);

    $mock = Mockery::mock(CourierService::class);
    $mock->shouldReceive('syncStatus')->never();
    app()->instance(CourierService::class, $mock);

    $this->artisan(RefreshCourierStatus::class)
        ->assertSuccessful();
});

it('preserves tenant isolation', function () {
    $otherTenant = actingAsTenant(['subdomain' => 'other-tenant', 'status' => 'active']);

    CourierConnection::query()->create([
        'tenant_id' => $otherTenant->id,
        'courier_provider_id' => $this->provider->id,
        'is_active' => true,
        'credentials' => [],
    ]);

    $fulfillment = OrderFulfillment::query()->create([
        'tenant_id' => $this->tenant->id,
        'order_id' => courierTestOrder($this->tenant, 'ORD-TEST-'.Str::random(6)),
        'status' => OrderFulfillmentStatus::Shipped->value,
        'tracking_number' => 'TRACK123',
        'courier_name' => 'Test Courier',
    ]);

    $mock = Mockery::mock(CourierService::class);
    $mock->shouldReceive('syncStatus')->never();
    app()->instance(CourierService::class, $mock);

    $this->artisan(RefreshCourierStatus::class)
        ->assertSuccessful();
});

it('does not abort batch on command failure', function () {
    CourierConnection::query()->create([
        'tenant_id' => $this->tenant->id,
        'courier_provider_id' => $this->provider->id,
        'is_active' => true,
        'credentials' => [],
    ]);

    $f1 = OrderFulfillment::query()->create([
        'tenant_id' => $this->tenant->id,
        'order_id' => courierTestOrder($this->tenant, 'ORD-TEST-'.Str::random(6)),
        'status' => OrderFulfillmentStatus::Shipped->value,
        'tracking_number' => 'TRACK123',
        'courier_name' => 'Test Courier',
    ]);

    $f2 = OrderFulfillment::query()->create([
        'tenant_id' => $this->tenant->id,
        'order_id' => courierTestOrder($this->tenant, 'ORD-TEST-'.Str::random(6)),
        'status' => OrderFulfillmentStatus::Shipped->value,
        'tracking_number' => 'TRACK456',
        'courier_name' => 'Test Courier',
    ]);

    $mock = Mockery::mock(CourierService::class);
    $mock->shouldReceive('syncStatus')
        ->with(Mockery::on(fn ($f) => $f->id === $f1->id), Mockery::any())
        ->andThrow(new Exception('Network error'));

    $mock->shouldReceive('syncStatus')
        ->with(Mockery::on(fn ($f) => $f->id === $f2->id), Mockery::any())
        ->once();

    app()->instance(CourierService::class, $mock);

    Log::shouldReceive('error')->once();

    $this->artisan(RefreshCourierStatus::class)
        ->assertSuccessful();
});

describe('resolving credentials by foreign key', function (): void {
    it('uses the connection recorded at shipment time, not the name', function () {
        // The FK is what actually created the consignment, so it must win. A
        // provider renamed after shipping would break name matching; this cannot.
        $recorded = CourierConnection::query()->create([
            'tenant_id' => $this->tenant->id,
            'courier_provider_id' => $this->provider->id,
            'is_active' => true,
            'credentials' => [],
        ]);

        $decoy = CourierConnection::query()->create([
            'tenant_id' => $this->tenant->id,
            'courier_provider_id' => CourierProvider::query()->create([
                'code' => 'decoy_courier',
                'name' => 'Renamed Courier',
                'is_active' => true,
            ])->id,
            'is_active' => true,
            'credentials' => [],
        ]);

        $fulfillment = OrderFulfillment::query()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => courierTestOrder($this->tenant, 'ORD-TEST-'.Str::random(6)),
            'status' => OrderFulfillmentStatus::Shipped->value,
            'tracking_number' => 'TRACK123',
            'courier_name' => 'Renamed Courier',
            'courier_connection_id' => $recorded->id,
        ]);

        $mock = Mockery::mock(CourierService::class);
        $mock->shouldReceive('syncStatus')->once()->with(
            Mockery::on(fn ($f) => $f->id === $fulfillment->id),
            Mockery::on(fn ($c) => $c->id === $recorded->id && $c->id !== $decoy->id)
        );
        app()->instance(CourierService::class, $mock);

        $this->artisan(RefreshCourierStatus::class)->assertSuccessful();
    });

    it('resolves by FK even when the name is ambiguous', function () {
        // Two providers sharing a label is exactly the case name matching cannot
        // handle. With the FK present there is nothing to guess.
        $connection = CourierConnection::query()->create([
            'tenant_id' => $this->tenant->id,
            'courier_provider_id' => $this->provider->id,
            'is_active' => true,
            'credentials' => [],
        ]);

        CourierConnection::query()->create([
            'tenant_id' => $this->tenant->id,
            'courier_provider_id' => CourierProvider::query()->create([
                'code' => 'test_courier_twin',
                'name' => 'Test Courier',
                'is_active' => true,
            ])->id,
            'is_active' => true,
            'credentials' => [],
        ]);

        $fulfillment = OrderFulfillment::query()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => courierTestOrder($this->tenant, 'ORD-TEST-'.Str::random(6)),
            'status' => OrderFulfillmentStatus::Shipped->value,
            'tracking_number' => 'TRACK123',
            'courier_name' => 'Test Courier',
            'courier_connection_id' => $connection->id,
        ]);

        $mock = Mockery::mock(CourierService::class);
        $mock->shouldReceive('syncStatus')->once()->with(
            Mockery::on(fn ($f) => $f->id === $fulfillment->id),
            Mockery::on(fn ($c) => $c->id === $connection->id)
        );
        app()->instance(CourierService::class, $mock);

        $this->artisan(RefreshCourierStatus::class)->assertSuccessful();
    });

    it('skips a consignment whose connection the merchant switched off', function () {
        // Polling with credentials the merchant deactivated is not something to do
        // quietly, and the name fallback must not be used to work around it.
        $connection = CourierConnection::query()->create([
            'tenant_id' => $this->tenant->id,
            'courier_provider_id' => $this->provider->id,
            'is_active' => false,
            'credentials' => [],
        ]);

        OrderFulfillment::query()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => courierTestOrder($this->tenant, 'ORD-TEST-'.Str::random(6)),
            'status' => OrderFulfillmentStatus::Shipped->value,
            'tracking_number' => 'TRACK123',
            'courier_name' => 'Test Courier',
            'courier_connection_id' => $connection->id,
        ]);

        $mock = Mockery::mock(CourierService::class);
        $mock->shouldReceive('syncStatus')->never();
        app()->instance(CourierService::class, $mock);

        Log::shouldReceive('info')->withArgs(fn ($msg) => str_contains($msg, 'is inactive for fulfillment'));

        $this->artisan(RefreshCourierStatus::class)->assertSuccessful();
    });

    it('hands a consignment back to the name fallback when its connection is deleted', function () {
        // nullOnDelete blanks the FK rather than deleting order history, so the row
        // degrades to exactly the pre-FK behaviour: name matching, skipped loudly
        // when nothing active matches.
        $connection = CourierConnection::query()->create([
            'tenant_id' => $this->tenant->id,
            'courier_provider_id' => $this->provider->id,
            'is_active' => true,
            'credentials' => [],
        ]);

        $fulfillment = OrderFulfillment::query()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => courierTestOrder($this->tenant, 'ORD-TEST-'.Str::random(6)),
            'status' => OrderFulfillmentStatus::Shipped->value,
            'tracking_number' => 'TRACK123',
            'courier_name' => 'Test Courier',
            'courier_connection_id' => $connection->id,
        ]);

        $connection->delete();

        expect(OrderFulfillment::query()->whereKey($fulfillment->id)->value('courier_connection_id'))->toBeNull();

        $mock = Mockery::mock(CourierService::class);
        $mock->shouldReceive('syncStatus')->never();
        app()->instance(CourierService::class, $mock);

        Log::shouldReceive('info')->withArgs(fn ($msg) => str_contains($msg, 'No active courier connection found'));

        $this->artisan(RefreshCourierStatus::class)->assertSuccessful();
    });

    it('still falls back to the name for rows shipped before the FK existed', function () {
        // Backfill deliberately leaves unresolvable rows NULL, so this path has to
        // keep working exactly as it did.
        $connection = CourierConnection::query()->create([
            'tenant_id' => $this->tenant->id,
            'courier_provider_id' => $this->provider->id,
            'is_active' => true,
            'credentials' => [],
        ]);

        $fulfillment = OrderFulfillment::query()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => courierTestOrder($this->tenant, 'ORD-TEST-'.Str::random(6)),
            'status' => OrderFulfillmentStatus::Shipped->value,
            'tracking_number' => 'TRACK123',
            'courier_name' => 'Test Courier',
            'courier_connection_id' => null,
        ]);

        $mock = Mockery::mock(CourierService::class);
        $mock->shouldReceive('syncStatus')->once()->with(
            Mockery::on(fn ($f) => $f->id === $fulfillment->id),
            Mockery::on(fn ($c) => $c->id === $connection->id)
        );
        app()->instance(CourierService::class, $mock);

        $this->artisan(RefreshCourierStatus::class)->assertSuccessful();
    });
});
