<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BannerMediaType;
use App\Enums\BannerPlacement;
use App\Enums\LinkType;
use App\Enums\PopupFrequency;
use App\Enums\Visibility;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasFlexibleLink;
use App\Models\Concerns\HasSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Banner extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFlexibleLink;
    use HasSchedule;
    use InteractsWithMedia;

    protected $fillable = [
        'campaign_id', 'title', 'placement', 'media_type', 'popup_frequency', 'visibility',
        'link_type', 'link_value', 'starts_at', 'ends_at', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'placement' => BannerPlacement::class,
            'media_type' => BannerMediaType::class,
            'popup_frequency' => PopupFrequency::class,
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')->singleFile();
        $this->addMediaCollection('video')->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('large')
            ->width(1600)
            ->fit(Fit::Max, 1600, 900)
            ->format('webp')
            ->performOnCollections('image');
    }
}
