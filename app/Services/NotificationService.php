<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SendNotificationJob;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Notifications\NotificationRecipient;
use App\Support\PlaceholderRenderer;

/**
 * Single gateway for all outbound notifications — customer-facing and internal
 * staff alerts both flow through here identically. No caller sends directly
 * via Mail/SMS/etc. — see EmailChannelDriver/SmsChannelDriver for the actual
 * transport, resolved here by channel key.
 *
 * Future extension (documented, not built): per-customer channel opt-in/opt-out
 * would be checked here, before a log/job is created, once real customer
 * preference data exists to check against.
 */
class NotificationService
{
    public function send(string $eventKey, NotificationRecipient $recipient, array $context = []): void
    {
        $templates = NotificationTemplate::query()
            ->where('event_key', $eventKey)
            ->where('is_active', true)
            ->get();

        foreach ($templates as $template) {
            $address = $recipient->addresses[$template->channel] ?? null;

            if (! $address) {
                continue;
            }

            $idempotencyKey = hash('sha256', implode('|', [
                tenant()->id, $eventKey, $template->channel, $address,
                $context['related_type'] ?? '', $context['related_id'] ?? '',
            ]));

            $alreadyHandled = NotificationLog::query()
                ->where('idempotency_key', $idempotencyKey)
                ->whereIn('status', ['queued', 'sent'])
                ->exists();

            if ($alreadyHandled) {
                continue;
            }

            $log = NotificationLog::query()->create([
                'tenant_id' => tenant()->id,
                'notification_template_id' => $template->id,
                'event_key' => $eventKey,
                'channel' => $template->channel,
                'recipient_type' => $recipient->modelType,
                'recipient_id' => $recipient->modelId,
                'recipient_address' => $address,
                'related_type' => $context['related_type'] ?? null,
                'related_id' => $context['related_id'] ?? null,
                'subject_rendered' => $template->subject ? PlaceholderRenderer::render($template->subject, $context) : null,
                'body_rendered' => PlaceholderRenderer::render($template->body, $context),
                'status' => 'queued',
                'idempotency_key' => $idempotencyKey,
            ]);

            SendNotificationJob::dispatch($log->id, $log->tenant_id);
        }
    }
}