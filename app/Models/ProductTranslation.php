<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Purifier\StorefrontPurifier;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductTranslation extends Model implements HasMedia, HasRichContent
{
    use BelongsToTenant, HasFactory, InteractsWithMedia, InteractsWithRichContent;

    protected $fillable = ['product_id', 'locale', 'name', 'slug', 'description', 'warranty_info', 'meta_title', 'meta_description'];

    protected function setUpRichContent(): void
    {
        $this->registerRichContent('description')
            ->fileAttachmentsVisibility('public')
            ->fileAttachmentProvider(
                SpatieMediaLibraryFileAttachmentProvider::make()->collection('description_images'),
            );
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('description_images');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('large')
            ->width(1200)
            ->fit(Fit::Contain, 1200, 1200)
            ->format('webp');
    }

    protected static function booted(): void
    {
        static::creating(function (self $translation): void {
            $translation->slug ??= Str::slug($translation->name);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Sanitizes the product description for storefront rendering.
     *
     * Uses the dedicated 'product' purifier profile so admin-authored rich
     * content (headings, lists, links, images, safe tables, callouts) survives
     * while any injected script/style is stripped. The result is cached under a
     * content-derived key, so editing the description naturally invalidates it
     * without requiring explicit cache flushing.
     */
    public function sanitizedDescription(): ?string
    {
        if ($this->description === null || $this->description === '') {
            return null;
        }

        return Cache::rememberForever(
            $this->sanitizedDescriptionCacheKey(),
            fn (): string => StorefrontPurifier::clean($this->description, 'product'),
        );
    }

    private function sanitizedDescriptionCacheKey(): string
    {
        return 'product-description:'.$this->product_id.':'.$this->locale.':'.md5($this->description);
    }
}
