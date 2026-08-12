<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductRelationType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Concerns\BelongsToTenant;
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
use Illuminate\Database\Eloquent\Builder;

class Product extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use InteractsWithMedia;
    use LogsActivity;
    use Searchable;
    use SoftDeletes;

    protected $fillable = [
        'brand_id', 'category_id', 'model_number', 'type', 'base_price',
        'status', 'is_featured', 'is_serialized', 'published_at', 'created_by', 'updated_by','is_official_import', 'max_discount_percentage', 'view_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'type' => ProductType::class,
            'is_featured' => 'boolean',
            'is_serialized' => 'boolean',
            'base_price' => 'integer',
            'published_at' => 'datetime',
            'is_official_import' => 'boolean',
            'max_discount_percentage' => 'integer',
            'view_count' => 'integer',
            'average_rating' => 'decimal:2',
            'reviews_count' => 'integer',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::make(get: fn () => $this->translation('en')?->name);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
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

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

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
        $translation = $this->translation('en');

        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $translation?->name,
            'description' => $translation?->description,
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