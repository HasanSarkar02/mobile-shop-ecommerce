<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\OrderService;
use App\Support\Tenancy\Tenancy;
use Illuminate\Console\Command;

class ReleaseExpiredReservations extends Command
{
    protected $signature = 'orders:release-expired-reservations';

    protected $description = 'Auto-cancels Pending orders whose stock reservation has expired.';

    public function handle(OrderService $orders): int
    {
        $total = 0;

        Tenant::query()->where('status', 'active')->each(function (Tenant $tenant) use ($orders, &$total): void {
            app(Tenancy::class)->set($tenant);
            $total += $orders->releaseExpiredReservations();
        });

        $this->info("Released {$total} expired reservation(s).");

        return self::SUCCESS;
    }
}
