<?php

declare(strict_types=1);

use App\Enums\DeploymentMode;
use App\Models\SchedulerHeartbeat;
use App\Services\SchedulerHeartbeatService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    config()->set('deployment.mode', DeploymentMode::SaaS->value);
    app(Tenancy::class)->set(null);
    Cache::flush();
});

it('records a heartbeat via the scheduled command', function (): void {
    $this->artisan('scheduler:heartbeat')->assertSuccessful();

    $this->assertDatabaseHas('scheduler_heartbeats', ['name' => 'application']);

    $heartbeat = SchedulerHeartbeat::query()->where('name', 'application')->first();
    expect($heartbeat->last_heartbeat_at)->not->toBeNull();
});

it('updates the existing heartbeat instead of creating duplicates', function (): void {
    $this->artisan('scheduler:heartbeat')->assertSuccessful();
    $this->artisan('scheduler:heartbeat')->assertSuccessful();

    expect(SchedulerHeartbeat::query()->where('name', 'application')->count())->toBe(1);
});

it('reports a healthy heartbeat after a successful ping', function (): void {
    app(SchedulerHeartbeatService::class)->ping(SchedulerHeartbeatService::NAME_APPLICATION);

    $status = app(SchedulerHeartbeatService::class)->status(SchedulerHeartbeatService::NAME_APPLICATION);

    expect($status['status'])->toBe('healthy')
        ->and($status['heartbeat_at'])->not->toBeNull()
        ->and($status['age_seconds'])->toBeLessThanOrEqual(60);
});

it('reports unhealthy before the first heartbeat', function (): void {
    $status = app(SchedulerHeartbeatService::class)->status(SchedulerHeartbeatService::NAME_APPLICATION);

    expect($status['status'])->toBe('unhealthy')
        ->and($status['heartbeat_at'])->toBeNull()
        ->and($status['age_seconds'])->toBeNull();
});

it('reports unhealthy when the heartbeat is stale', function (): void {
    SchedulerHeartbeat::query()->create([
        'name' => 'application',
        'last_heartbeat_at' => now()->subMinutes(30),
    ]);

    $status = app(SchedulerHeartbeatService::class)->status(SchedulerHeartbeatService::NAME_APPLICATION);

    expect($status['status'])->toBe('unhealthy');
});
