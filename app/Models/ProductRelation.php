<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductRelationType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRelation extends Model
{
    use BelongsToTenant;

    protected $fillable = ['product_id', 'related_product_id', 'type', 'sort_order'];

    protected function casts(): array
    {
        return ['type' => ProductRelationType::class];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function relatedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'related_product_id');
    }
}