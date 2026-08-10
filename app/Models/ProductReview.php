<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductReview extends Model
{
    use BelongsToTenant;

    protected $fillable = ['product_id', 'customer_id', 'rating', 'title', 'body', 'status', 'is_verified_purchase'];

    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'rating' => 'integer',
            'is_verified_purchase' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}