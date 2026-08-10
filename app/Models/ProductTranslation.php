<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductTranslation extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['product_id', 'locale', 'name', 'slug', 'description', 'meta_title', 'meta_description'];

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

    public function sanitizedDescription(): ?string
    {
        return $this->description ? \Mews\Purifier\Facades\Purifier::clean($this->description) : null;
    }
}