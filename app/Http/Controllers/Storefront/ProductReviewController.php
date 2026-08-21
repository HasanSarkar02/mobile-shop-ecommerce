<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductReviewController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $customer = Auth::guard('customer')->user();

        $isVerifiedPurchase = OrderItem::query()
            ->whereHas('order', fn ($q) => $q->where('customer_id', $customer->id)
                ->whereIn('status', ['confirmed', 'processing', 'shipped', 'delivered']))
            ->whereHas('variant', fn ($q) => $q->where('product_id', $product->id))
            ->exists();

        ProductReview::query()->updateOrCreate(
            ['product_id' => $product->id, 'customer_id' => $customer->id],
            [
                'tenant_id' => $product->tenant_id,
                'rating' => $data['rating'],
                'title' => $data['title'] ?? null,
                'body' => $data['body'],
                'status' => 'pending',
                'is_verified_purchase' => $isVerifiedPurchase,
            ],
        );

        return back()->with('status', 'Thanks! Your review will appear after moderation.');
    }
}
