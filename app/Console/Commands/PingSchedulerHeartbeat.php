<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SchedulerHeartbeatService;
use Illuminate\Console\Command;

class PingSchedulerHeartbeat extends Command
{
    protected $signature = 'scheduler:heartbeat {--name=application : Heartbeat name (one row per name)}';

    protected $description = 'Record a successful scheduler heartbeat';

    public function handle(SchedulerHeartbeatService $heartbeats): int
    {
        $heartbeats->ping((string) $this->option('name'));

        $this->info('Scheduler heartbeat recorded.');

        return self::SUCCESS;
    }
}
