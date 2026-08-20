<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use App\Console\Commands\ReleaseExpiredReservations;
use Illuminate\Support\Facades\Schedule;

Schedule::command(ReleaseExpiredReservations::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

use App\Console\Commands\ProcessSubscriptionExpirations;

Schedule::command(ProcessSubscriptionExpirations::class)->daily();

use App\Console\Commands\DispatchDomainDnsChecks;

Schedule::command(DispatchDomainDnsChecks::class)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

use App\Console\Commands\ProcessSubscriptionReminders;

Schedule::command(ProcessSubscriptionReminders::class)
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->onOneServer();

use App\Console\Commands\PingSchedulerHeartbeat;

Schedule::command(PingSchedulerHeartbeat::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
