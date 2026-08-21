<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Brand extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'slug', 'logo_path', 'meta_title', 'meta_description'];

    protected static function booted(): void
    {
        static::creating(function (self $brand): void {
            $brand->slug ??= Str::slug($brand->name);
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
