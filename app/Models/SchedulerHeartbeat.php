<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchedulerHeartbeat extends Model
{
    protected $fillable = [
        'name',
        'last_heartbeat_at',
    ];

    protected function casts(): array
    {
        return [
            'last_heartbeat_at' => 'datetime',
        ];
    }
}
