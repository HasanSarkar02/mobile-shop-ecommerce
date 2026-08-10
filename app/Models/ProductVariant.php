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
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Enums\BackorderPolicy;
use App\Enums\FulfillmentStrategy;
use App\Enums\InventoryType;

class ProductVariant extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use InteractsWithMedia;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
    'product_id', 'sku', 'barcode', 'color', 'storage_gb', 'ram_gb', 'sim_type', 'region',
    'price', 'compare_at_price', 'cost_price',
    'weight_grams', 'length_mm', 'width_mm', 'height_mm',
    'availability', 'expected_available_at', 'is_active',
    'inventory_type', 'fulfillment_strategy', 'backorder_policy', 'low_stock_threshold',
];

    protected function casts(): array
{
    return [
        'price' => 'integer',
        'compare_at_price' => 'integer',
        'cost_price' => 'integer',
        'storage_gb' => 'integer',
        'ram_gb' => 'integer',
        'availability' => VariantAvailability::class,
        'inventory_type' => InventoryType::class,
        'fulfillment_strategy' => FulfillmentStrategy::class,
        'backorder_policy' => BackorderPolicy::class,
        'low_stock_threshold' => 'integer',
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

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class);
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }

    public function isPreOrder(): bool
    {
        return $this->fulfillment_strategy === FulfillmentStrategy::Preorder;
    }

    public function discountPercentage(): ?int
    {
        if (! $this->compare_at_price || $this->compare_at_price <= $this->price) {
            return null;
        }

        return (int) round((($this->compare_at_price - $this->price) / $this->compare_at_price) * 100);
    }

    public function marginPercentage(): ?int
    {
        if (! $this->cost_price || $this->price <= 0) {
            return null;
        }

        return (int) round((($this->price - $this->cost_price) / $this->price) * 100);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->width(300)->height(300)->fit(Fit::Contain, 300, 300)->format('webp');
        $this->addMediaConversion('large')->width(1200)->format('webp');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['price', 'compare_at_price', 'availability'])
            ->logOnlyDirty();
    }
}