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

    protected $fillable = ['product_id', 'customer_id', 'rating', 'title', 'body', 'status', 'is_verified_purchase', 'reply_text', 'replied_at'];

    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'rating' => 'integer',
            'is_verified_purchase' => 'boolean',
            'replied_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $review): void {
            if ($review->isDirty('reply_text')) {
                $review->replied_at = filled($review->reply_text) ? now()->toDateTimeString() : null;
            }
        });
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
