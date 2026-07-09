<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VariantAvailability;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductVariant extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $fillable = [
        'product_id', 'sku', 'barcode', 'color', 'storage_gb', 'ram_gb', 'sim_type',
        'price', 'compare_at_price', 'currency',
        'weight_grams', 'length_mm', 'width_mm', 'height_mm',
        'availability', 'expected_available_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'compare_at_price' => 'integer',
            'storage_gb' => 'integer',
            'ram_gb' => 'integer',
            'availability' => VariantAvailability::class,
            'expected_available_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function isPreOrder(): bool
    {
        return $this->availability === VariantAvailability::PreOrder;
    }

    public function discountPercentage(): ?int
    {
        if (! $this->compare_at_price || $this->compare_at_price <= $this->price) {
            return null;
        }

        return (int) round((($this->compare_at_price - $this->price) / $this->compare_at_price) * 100);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->width(300)->height(300)->fit(Fit::Contain, 300, 300)->format('webp')->nonQueued();
        $this->addMediaConversion('large')->width(1200)->format('webp')->nonQueued();
    }
}