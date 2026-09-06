<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductRelationType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string|null $name
 * @property string|null $model_number
 */
class Product extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use InteractsWithMedia;
    use LogsActivity;
    use Searchable;
    use SoftDeletes;

    protected $fillable = [
        'brand_id', 'category_id', 'model_number', 'type', 'base_price', 'uom_id', 'sell_by_unit', 'base_uom_quantity',
        'status', 'is_featured', 'is_serialized', 'published_at', 'created_by', 'updated_by', 'is_official_import', 'max_discount_percentage', 'view_count', 'sold_quantity',
        'rating_1_count', 'rating_2_count', 'rating_3_count', 'rating_4_count', 'rating_5_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'type' => ProductType::class,
            'is_featured' => 'boolean',
            'is_serialized' => 'boolean',
            'base_price' => 'integer',
            'sell_by_unit' => 'decimal:3',
            'base_uom_quantity' => 'decimal:3',
            'published_at' => 'datetime',
            'is_official_import' => 'boolean',
            'max_discount_percentage' => 'integer',
            'view_count' => 'integer',
            'sold_quantity' => 'decimal:3',
            'rating_1_count' => 'integer',
            'rating_2_count' => 'integer',
            'rating_3_count' => 'integer',
            'rating_4_count' => 'integer',
            'rating_5_count' => 'integer',
            'average_rating' => 'decimal:2',
            'reviews_count' => 'integer',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::make(get: function (): ?string {
            $t = $this->translation();
            if ($t !== null && $t->name !== '') {
                return $t->name;
            }

            return $this->translation('en')?->name;
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Sell unit for measured goods (Phase C-1, e.g. kg for rice). Null means
     * discrete whole-unit selling — the historical default.
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    public function translation(?string $locale = null): ?ProductTranslation
    {
        return $this->translations->firstWhere('locale', $locale ?? app()->getLocale());
    }

    /** @return HasMany<ProductVariant> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return HasMany<ProductAttributeValue> */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function emiPlans(): BelongsToMany
    {
        return $this->belongsToMany(EmiPlan::class, 'product_emi_plan');
    }

    public function productRelations(): HasMany
    {
        return $this->hasMany(ProductRelation::class);
    }

    public function relatedProducts(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_relations', 'product_id', 'related_product_id')
            ->wherePivot('type', ProductRelationType::Related->value);
    }

    public function crossSells(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_relations', 'product_id', 'related_product_id')
            ->wherePivot('type', ProductRelationType::CrossSell->value);
    }

    public function upsells(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_relations', 'product_id', 'related_product_id')
            ->wherePivot('type', ProductRelationType::Upsell->value);
    }

    public function frequentlyBoughtWith(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_relations', 'product_id', 'related_product_id')
            ->wherePivot('type', ProductRelationType::FrequentlyBoughtTogether->value);
    }

    public function compatibleAccessories(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_relations', 'product_id', 'related_product_id')
            ->wherePivot('type', ProductRelationType::Compatible->value);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->width(300)->height(300)->fit(Fit::Contain, 300, 300)->format('webp');
        $this->addMediaConversion('small')->width(240)->height(240)->fit(Fit::Contain, 240, 240)->format('webp');
        $this->addMediaConversion('large')->width(1200)->format('webp');
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === ProductStatus::Published;
    }

    public function toSearchableArray(): array
    {
        // Locale-aware: index all translations so Scout (database driver for
        // now, Meilisearch later) matches either language. Keep `name` as
        // combined for backward compat, plus per-locale fields for future
        // locale-scoped ranking.
        $names = $this->relationLoaded('translations')
            ? $this->translations->pluck('name')->filter()->implode(' ')
            : '';
        $descriptions = $this->relationLoaded('translations')
            ? $this->translations->pluck('description')->filter()->implode(' ')
            : '';
        $slugs = $this->relationLoaded('translations')
            ? $this->translations->pluck('slug')->filter()->implode(' ')
            : '';

        // Fallback when relation not yet loaded (e.g. during factory creation before translations exist).
        if ($names === '' && $slugs === '') {
            if ($this->relationLoaded('translations') && $this->translations->isNotEmpty()) {
                // already handled
            } else {
                $t = $this->translation() ?? $this->translation('en');
                $names = $t !== null ? (string) $t->name : '';
                $descriptions = $t !== null ? (string) ($t->description ?? '') : '';
                $slugs = $t !== null ? (string) ($t->slug ?? '') : '';
                // Also merge all translations if not loaded but exist in DB
                if ($this->exists && ! $this->relationLoaded('translations')) {
                    $all = $this->translations()->pluck('name')->filter()->implode(' ');
                    if ($all !== '') {
                        $names = $all;
                    }
                    $descAll = $this->translations()->pluck('description')->filter()->implode(' ');
                    if ($descAll !== '') {
                        $descriptions = $descAll;
                    }
                    $slugAll = $this->translations()->pluck('slug')->filter()->implode(' ');
                    if ($slugAll !== '') {
                        $slugs = $slugAll;
                    }
                }
            }
        }

        // Tags — imploded names for deep search
        $tags = $this->relationLoaded('tags')
            ? $this->tags->pluck('name')->filter()->implode(' ')
            : ($this->exists ? $this->tags()->pluck('name')->filter()->implode(' ') : '');

        // Variants — SKU and barcode are critical for merchant/CS SKU search
        $variantSkus = '';
        $variantBarcodes = '';
        if ($this->relationLoaded('variants')) {
            $variantSkus = $this->variants->pluck('sku')->filter()->implode(' ');
            $variantBarcodes = $this->variants->pluck('barcode')->filter()->implode(' ');
        } elseif ($this->exists) {
            $variantSkus = $this->variants()->pluck('sku')->filter()->implode(' ');
            $variantBarcodes = $this->variants()->pluck('barcode')->filter()->implode(' ');
        }

        // Brand / Category names — denormalized for cross-entity matching
        $brandName = '';
        if ($this->relationLoaded('brand') && $this->brand !== null) {
            $brandName = (string) ($this->brand->getAttribute('name') ?? '');
        } elseif ($this->exists && $this->brand_id !== null) {
            $brandName = (string) ($this->brand()->value('name') ?? '');
        }

        $categoryName = '';
        if ($this->relationLoaded('category') && $this->category !== null) {
            $categoryName = (string) ($this->category->getAttribute('name') ?? '');
        } elseif ($this->exists && $this->category_id !== null) {
            $categoryName = (string) ($this->category()->value('name') ?? '');
        }

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $names,
            'description' => $descriptions,
            'name_en' => $this->translation('en')?->name,
            'name_bn' => $this->translation('bn')?->name,
            'slug' => $slugs,
            'model_number' => $this->model_number,
            'brand' => $brandName,
            'category' => $categoryName,
            'tags' => $tags,
            'sku' => $variantSkus,
            'barcode' => $variantBarcodes,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status'])
            ->logOnlyDirty();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Published);
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_product')->withPivot('sort_order');
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereHas('variants', function (Builder $v): void {
                $v->where('is_active', true)
                    ->where(function (Builder $vv): void {
                        $vv->whereIn('fulfillment_strategy', ['preorder', 'dropship'])
                            ->orWhereHas('stockItems', fn (Builder $s) => $s->whereRaw('quantity - reserved_quantity > 0'));
                    });
            });
        });
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('status', 'approved')->latest();
    }
}
