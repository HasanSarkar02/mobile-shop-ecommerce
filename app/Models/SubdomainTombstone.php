<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TombstoneReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubdomainTombstone extends Model
{
    protected $fillable = [
        'subdomain', 'tenant_id', 'reason', 'released_at', 'quarantine_until', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'reason' => TombstoneReason::class,
            'released_at' => 'datetime',
            'quarantine_until' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isQuarantined(): bool
    {
        return $this->quarantine_until !== null && $this->quarantine_until->isFuture();
    }

    public function isPermanent(): bool
    {
        return $this->reason === TombstoneReason::Abuse && $this->quarantine_until === null;
    }
}
