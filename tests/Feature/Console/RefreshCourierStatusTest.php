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
