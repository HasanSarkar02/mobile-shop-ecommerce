<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Models\NotificationLog;
use App\Models\Tenant;
use App\Support\Tenancy\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * $tenantId is carried explicitly because this job runs on a queue worker
     * process, which never goes through the IdentifyTenant HTTP middleware.
     * The Tenancy binding is `scoped`, so it starts empty for every job and
     * must be restored here before any tenant-scoped model (NotificationLog)
     * is touched.
     */
    public function __construct(
        private readonly int $notificationLogId,
        private readonly int $tenantId,
    ) {
    }

    /** @return list<int> seconds before each retry: 30s, 2min, 5min */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        $this->withTenantContext(function (): void {
            $log = NotificationLog::query()->findOrFail($this->notificationLogId);

            $log->increment('attempts');

            $driverClass = config("notification_channels.drivers.{$log->channel}");

            if (! $driverClass) {
                $log->update([
                    'status' => NotificationStatus::Failed,
                    'error_message' => "No driver configured for channel '{$log->channel}'.",
                ]);

                return;
            }

            app($driverClass)->send($log->recipient_address, $log->subject_rendered, $log->body_rendered);

            $log->update(['status' => NotificationStatus::Sent, 'sent_at' => now()]);
        });
    }

    public function failed(\Throwable $exception): void
    {
        $this->withTenantContext(function () use ($exception): void {
            NotificationLog::query()->where('id', $this->notificationLogId)->update([
                'status' => NotificationStatus::Failed,
                'error_message' => $exception->getMessage(),
            ]);
        });
    }

    private function withTenantContext(\Closure $callback): void
    {
        $tenancy = app(Tenancy::class);
        $tenancy->set(Tenant::query()->findOrFail($this->tenantId));

        try {
            $callback();
        } finally {
            $tenancy->set(null);
        }
    }
}