<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductReview;

class ProductReviewObserver
{
    public function saved(ProductReview $review): void
    {
        $this->recalculate($review->product_id);
    }

    public function deleted(ProductReview $review): void
    {
        $this->recalculate($review->product_id);
    }

    private function recalculate(int $productId): void
    {
        $approved = ProductReview::query()->where('product_id', $productId)->where('status', 'approved')->get();

        Product::query()->where('id', $productId)->update([
            'average_rating' => $approved->isNotEmpty() ? round($approved->avg('rating'), 2) : null,
            'reviews_count' => $approved->count(),
        ]);
    }
}