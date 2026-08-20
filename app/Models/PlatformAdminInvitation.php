<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAdminInvitation extends Model
{
    protected $fillable = [
        'user_id',
        'invited_by',
        'token_digest',
        'issued_at',
        'expires_at',
        'accepted_at',
        'consumed_at',
        'revoked_at',
        'resend_count',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'consumed_at' => 'datetime',
            'revoked_at' => 'datetime',
            'resend_count' => 'integer',
        ];
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
}
