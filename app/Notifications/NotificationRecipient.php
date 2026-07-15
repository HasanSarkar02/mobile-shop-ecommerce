<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Channel-agnostic recipient descriptor. Works identically for customer-facing
 * and internal staff notifications — the audience distinction is metadata only,
 * NotificationService treats both the same way.
 */
final class NotificationRecipient
{
    /** @param array<string,string> $addresses channel key => address (email/phone/etc.) */
    public function __construct(
        public readonly string $audience,
        public readonly ?string $modelType = null,
        public readonly ?int $modelId = null,
        public readonly array $addresses = [],
    ) {
    }
}