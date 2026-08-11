<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Jobs\IncrementProductViewCount;
use App\Models\Product;
use App\Services\CompareService;
use App\Services\RecentlyViewedService;
use App\Services\WishlistService;

class ProductController extends Controller
{
    public function show(string $slug, RecentlyViewedService $recentlyViewed, WishlistService $wishlists, CompareService $compare)
    {
        $product = Product::published()
            ->whereHas('translations', fn ($query) => $query->where('slug', $slug))
            ->with([
                'translations',
                'brand',
                'category',
                'variants.media',
                'media',
                'attributeValues.attributeDefinition',
                'attributeValues.attributeOption',
                'emiPlans' => fn ($query) => $query->where('active', true),
                'faqs' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
                'approvedReviews.customer',
            ])
            ->firstOrFail();

        IncrementProductViewCount::dispatch($product->id, $product->tenant_id);
        $recentlyViewed->record($product, auth('customer')->user());

        $wishlist = $wishlists->getOrCreateWishlist(auth('customer')->user(), request()->cookie('wishlist_token'));
        $isWishlisted = $wishlist->items()->where('product_id', $product->id)->exists();
        $isComparing = in_array($product->id, $compare->ids(), true);

        $variantsData = $product->variants->map(fn ($variant) => [
            'id' => $variant->id,
            'color' => $variant->color,
            'storage_gb' => $variant->storage_gb,
            'region' => $variant->region,
            'price' => $variant->price,
            'compare_at_price' => $variant->compare_at_price,
            'availability' => $variant->availability->value,
            'fulfillment_strategy' => $variant->fulfillment_strategy->value,
            'images' => $variant->getMedia('images')->map(fn ($media) => $media->getUrl('large'))->all(),
        ])->values();

        $productImages = $product->getMedia('images')->map(fn ($media) => $media->getUrl('large'))->all();
        $specifications = $product->attributeValues->whereNull('product_variant_id');
        $translation = $product->translation('en');

        $productJsonLd = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $translation?->name,
            'image' => $productImages,
            'description' => $translation?->description ? strip_tags($translation->description) : null,
            'sku' => $product->variants->first()?->sku,
            'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
            'offers' => $variantsData->map(fn ($v) => [
                '@type' => 'Offer',
                'price' => number_format($v['price'] / 100, 2, '.', ''),
                'priceCurrency' => tenant()->currency,
                'availability' => $v['availability'] === 'in_stock' ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            ])->all(),
            'aggregateRating' => $product->reviews_count > 0 ? [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $product->average_rating,
                'reviewCount' => (string) $product->reviews_count,
            ] : null,
        ]);

        $faqJsonLd = $product->faqs->isNotEmpty() ? [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $product->faqs->map(fn ($faq) => [
                '@type' => 'Question',
                'name' => $faq->question,
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($faq->answer)],
            ])->all(),
        ] : null;

        $relatedProducts = $product->relatedProducts()
            ->with(['translations', 'variants', 'media', 'emiPlans'])
            ->published()
            ->limit(4)
            ->get();

        return view('storefront.products.show', compact(
            'product', 'variantsData', 'productImages', 'specifications', 'productJsonLd', 'faqJsonLd', 'isWishlisted', 'isComparing',
            'relatedProducts',
        ));
    }
}