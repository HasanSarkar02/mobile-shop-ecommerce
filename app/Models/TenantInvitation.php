<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantInvitation extends Model
{
    public const SOURCE_PLATFORM = 'platform';

    public const SOURCE_PUBLIC_SIGNUP = 'public_signup';

    public const SOURCE_PASSWORD_RESET = 'password_reset';

    public const PURPOSE_OWNER_ONBOARDING = 'owner_onboarding';

    public const PURPOSE_OWNER_TRANSFER = 'owner_transfer';

    public const PURPOSE_PASSWORD_RESET = 'password_reset';

    public const DELIVERY_QUEUED = 'queued';

    public const DELIVERY_SENT = 'sent';

    public const DELIVERY_FAILED = 'failed';

    public const DELIVERY_REVOKED = 'revoked';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'invited_by',
        'source',
        'purpose',
        'previous_primary_owner_id',
        'token_digest',
        'issued_at',
        'expires_at',
        'sent_at',
        'opened_at',
        'accepted_at',
        'consumed_at',
        'revoked_at',
        'resend_count',
        'delivery_status',
        'delivery_error',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'accepted_at' => 'datetime',
            'consumed_at' => 'datetime',
            'revoked_at' => 'datetime',
            'resend_count' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        $expiresAt = $this->getAttribute('expires_at');

        return ! $expiresAt instanceof CarbonInterface || $expiresAt->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->getAttribute('consumed_at') !== null;
    }

    public function isRevoked(): bool
    {
        return $this->getAttribute('revoked_at') !== null;
    }
}
