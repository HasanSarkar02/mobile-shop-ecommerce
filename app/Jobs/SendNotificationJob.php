<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Models\NotificationLog;
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

    public function __construct(private readonly int $notificationLogId)
    {
    }

    /** @return list<int> seconds before each retry: 30s, 2min, 5min */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
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
    }

    public function failed(\Throwable $exception): void
    {
        NotificationLog::query()->where('id', $this->notificationLogId)->update([
            'status' => NotificationStatus::Failed,
            'error_message' => $exception->getMessage(),
        ]);
    }
}