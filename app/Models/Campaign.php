<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Campaign extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'slug', 'description', 'status', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $campaign): void {
            $campaign->slug ??= Str::slug($campaign->name);
        });
    }

    public function banners(): HasMany
    {
        return $this->hasMany(Banner::class);
    }

    public function homepageSections(): HasMany
    {
        return $this->hasMany(HomepageSection::class);
    }
}