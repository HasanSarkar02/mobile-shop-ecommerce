<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SubscriptionReminderService;
use Illuminate\Console\Command;

class ProcessSubscriptionReminders extends Command
{
    protected $signature = 'subscriptions:process-reminders';

    protected $description = 'Dispatch subscription charge due/overdue reminder emails to tenant owners';

    public function handle(SubscriptionReminderService $reminders): int
    {
        $dispatched = $reminders->process();

        $this->info("Dispatched {$dispatched} subscription reminder notification(s).");

        return self::SUCCESS;
    }
}
