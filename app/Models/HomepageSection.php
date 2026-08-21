<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HomepageSectionType;
use App\Enums\LinkType;
use App\Enums\Visibility;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasFlexibleLink;
use App\Models\Concerns\HasSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomepageSection extends Model
{
    use BelongsToTenant;
    use HasFlexibleLink;
    use HasSchedule;

    protected $fillable = [
        'campaign_id', 'type', 'title', 'config', 'visibility',
        'link_type', 'link_value', 'starts_at', 'ends_at', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => HomepageSectionType::class,
            'config' => 'array',
            'visibility' => Visibility::class,
            'link_type' => LinkType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
