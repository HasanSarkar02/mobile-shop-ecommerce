<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DomainStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    protected $fillable = [
        'tenant_id',
        'domain',
        'normalized_domain',
        'status',
        'verification_method',
        'verification_token_digest',
        'verification_record_name',
        'verification_started_at',
        'verification_expires_at',
        'verification_attempts',
        'last_checked_at',
        'verification_failure_code',
        'verification_failure_message',
        'activated_at',
        'revoked_at',
        'revocation_reason',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DomainStatus::class,
            'verified_at' => 'datetime',
            'verification_started_at' => 'datetime',
            'verification_expires_at' => 'datetime',
            'verification_attempts' => 'integer',
            'last_checked_at' => 'datetime',
            'activated_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
