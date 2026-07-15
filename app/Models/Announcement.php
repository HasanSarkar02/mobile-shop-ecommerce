<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LinkType;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasFlexibleLink;
use App\Models\Concerns\HasSchedule;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use BelongsToTenant;
    use HasFlexibleLink;
    use HasSchedule;

    protected $fillable = ['message', 'link_type', 'link_value', 'starts_at', 'ends_at', 'is_active', 'is_dismissible'];

    protected function casts(): array
    {
        return [
            'link_type' => LinkType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'is_dismissible' => 'boolean',
        ];
    }
}