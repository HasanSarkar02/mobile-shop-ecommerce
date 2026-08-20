<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SchedulerHeartbeat;
use Carbon\CarbonInterface;

/**
 * Smallest explicit scheduler heartbeat. The scheduled command pings this
 * service after a successful run and the platform dashboard reads the status
 * back. One row per name (unique constraint prevents duplicates); nothing here
 * introspects worker processes.
 */
class SchedulerHeartbeatService
{
    public const NAME_APPLICATION = 'application';

    /** A heartbeat is healthy if it was recorded within this many seconds. */
    public const STALE_AFTER_SECONDS = 300;

    public function ping(string $name): void
    {
        $heartbeat = SchedulerHeartbeat::query()->firstOrNew(['name' => $name]);
        $heartbeat->setAttribute('last_heartbeat_at', now());
        $heartbeat->save();
    }

    /**
     * @return array{heartbeat_at: ?string, age_seconds: ?int, status: string}
     */
    public function status(string $name): array
    {
        $heartbeat = SchedulerHeartbeat::query()->where('name', $name)->first();

        if ($heartbeat === null) {
            return [
                'heartbeat_at' => null,
                'age_seconds' => null,
                'status' => 'unhealthy',
            ];
        }

        $lastHeartbeat = $heartbeat->getAttribute('last_heartbeat_at');

        if (! $lastHeartbeat instanceof CarbonInterface) {
            return [
                'heartbeat_at' => null,
                'age_seconds' => null,
                'status' => 'unhealthy',
            ];
        }

        $age = max(0, (int) $lastHeartbeat->diffInSeconds(now()));

        return [
            'heartbeat_at' => $lastHeartbeat->toDateTimeString(),
            'age_seconds' => $age,
            'status' => $age <= self::STALE_AFTER_SECONDS ? 'healthy' : 'unhealthy',
        ];
    }
}
