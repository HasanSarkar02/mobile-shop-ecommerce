<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Campaign extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name', 'slug', 'description', 'accent_color', 'short_tagline', 'hero_image', 'card_image',
        'status', 'starts_at', 'ends_at',
    ];

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

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    public function homepageSections(): HasMany
    {
        return $this->hasMany(HomepageSection::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    /**
     * Single source of storefront eligibility: active status and inside its
     * start/end window (open-ended where null). Every public surface — offer
     * pages, campaign product grids, coupon rows — resolves visibility
     * through this scope so the rule can never drift between them.
     */
    public function scopeEligible(Builder $query): Builder
    {
        return $query->where('status', CampaignStatus::Active)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function isEligible(): bool
    {
        return static::query()->whereKey($this->getKey())->eligible()->exists();
    }

    /**
     * Campaign artwork URLs with a predictable fallback hierarchy. The
     * banners relation should be eager-loaded (controllers do) so the banner
     * fallback never triggers a query; getFirstMediaUrl returns '' when the
     * media is missing, normalized to null here.
     */
    public function heroImageUrl(): ?string
    {
        if ($this->hero_image) {
            return asset('storage/'.$this->hero_image);
        }

        $banner = $this->banners()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->first();

        if (! $banner instanceof Banner) {
            return null;
        }

        return $banner->largeImageUrl();
    }

    public function cardImageUrl(): ?string
    {
        if ($this->card_image) {
            return asset('storage/'.$this->card_image);
        }

        return $this->heroImageUrl();
    }
}
