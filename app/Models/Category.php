<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    use BelongsToTenant;

    protected $fillable = ['parent_id', 'name', 'slug', 'description'];

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
}