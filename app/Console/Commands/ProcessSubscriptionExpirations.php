<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ProcessSubscriptionExpirations extends Command
{
    protected $signature = 'subscriptions:process-expirations';

    protected $description = 'Expires subscriptions past their current period end date.';

    public function handle(SubscriptionService $subscriptions): int
    {
        $count = $subscriptions->processExpirations();
        $this->info("Expired {$count} subscription(s).");

        return self::SUCCESS;
    }
}