<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use BelongsToTenant;

    protected $fillable = ['parent_id', 'name', 'slug', 'description', 'image_path', 'meta_title', 'meta_description'];

    protected static function booted(): void
    {
        static::creating(function (self $category): void {
            $category->slug ??= Str::slug($category->name);
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function attributeDefinitions(): BelongsToMany
    {
        return $this->belongsToMany(AttributeDefinition::class, 'category_attribute_definition');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function hasRecordedValues(): bool
    {
        return $this->attributeValues()->exists();
    }

    /**
     * All descendant IDs including self, tenant-scoped via BelongsToTenant.
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        /** @var array<int, int> $pending */
        $pending = [$this->id];

        while ($pending !== []) {
            $children = static::query()->whereIn('parent_id', $pending)->pluck('id')->all();
            if ($children === []) {
                break;
            }
            $ids = array_merge($ids, $children);
            $pending = $children;
        }

        return $ids;
    }

    /**
     * Inclusive published product count (self + all descendants).
     */
    public function inclusiveProductsCount(): int
    {
        return Product::published()->whereIn('category_id', $this->descendantIds())->count();
    }

    /**
     * Scope to add inclusive products_count alias (self + descendants).
     * Uses PHP BFS to avoid recursive CTE complexity for shallow trees.
     */
    public function scopeWithInclusiveProductsCount(Builder $query): Builder
    {
        // We cannot do a single SQL subquery for arbitrary depth without CTE,
        // so this scope is intentionally not used for bulk header queries.
        // Bulk callers should use descendantIds() + whereIn count per parent.
        return $query;
    }
}
