<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Jobs\IncrementProductViewCount;
use App\Models\Product;
use App\Models\ProductAttributeValue;
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
                'variants.attributeValues.attributeDefinition',
                'variants.attributeValues.attributeOption',
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

        $dimensions = [];
        $variantsData = $product->variants->map(function ($variant) use (&$dimensions) {
            $dims = [];
            $meta = [];

            // Native phone/electronics columns: source of truth when populated.
            if ($variant->color !== null) {
                $dims['color'] = (string) $variant->color;
                $meta['color'] ??= ['code' => 'color', 'label' => 'Color', 'suffix' => ''];
            }
            if ($variant->storage_gb !== null) {
                $dims['storage'] = (string) $variant->storage_gb;
                $meta['storage'] ??= ['code' => 'storage', 'label' => 'Storage', 'suffix' => 'GB'];
            }
            if ($variant->region !== null) {
                $dims['region'] = (string) $variant->region;
                $meta['region'] ??= ['code' => 'region', 'label' => 'Region', 'suffix' => ''];
            }

            // Generic variant-defining attributes (Size, Weight, Shade, ...).
            foreach ($variant->attributeValues as $value) {
                if ($value->product_variant_id === null
                    || $value->attributeDefinition === null
                    || ! $value->attributeDefinition->is_variant_defining) {
                    continue;
                }

                $code = $value->attributeDefinition->code;
                $dims[$code] ??= $value->displayValue() ?? (string) ($value->attributeOption?->value ?? '');
                $meta[$code] ??= [
                    'code' => $code,
                    'label' => $value->attributeDefinition->label,
                    'suffix' => (string) ($value->attributeDefinition->unit ?? ''),
                ];
            }

            foreach ($meta as $code => $definition) {
                $dimensions[$code] ??= $definition;
            }

            return [
                'id' => $variant->id,
                'price' => $variant->price,
                'compare_at_price' => $variant->compare_at_price,
                'availability' => $variant->availability->value,
                'fulfillment_strategy' => $variant->fulfillment_strategy->value,
                'images' => $variant->getMedia('images')->map(fn ($media) => $media->getUrl('large'))->all(),
                'dims' => $dims,
            ];
        })->values();

        $dimensions = array_values($dimensions);

        $productImages = $product->getMedia('images')->map(fn ($media) => $media->getUrl('large'))->all();

        // Product-level specifications grouped by attribute group, ordered by
        // group_sort_order (groups) then sort_order (attributes within a group).
        // Definitions without a group fall back to the generic "General" group,
        // so legacy products keep rendering without any data migration.
        $specificationGroups = $product->attributeValues
            ->whereNull('product_variant_id')
            ->filter(fn (ProductAttributeValue $value) => $value->attributeDefinition !== null)
            ->groupBy(fn (ProductAttributeValue $value) => $value->attributeDefinition->group ?: 'General')
            ->map(function ($items, string $group): array {
                return [
                    'group' => $group,
                    'group_sort_order' => $items->min(fn (ProductAttributeValue $value) => $value->attributeDefinition->group_sort_order ?? 0),
                    'items' => $items
                        ->sortBy(fn (ProductAttributeValue $value) => $value->attributeDefinition->sort_order ?? 0)
                        ->values(),
                ];
            })
            ->sortBy(fn (array $group): array => [$group['group_sort_order'], $group['group']])
            ->values();
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
            'product', 'variantsData', 'dimensions', 'productImages', 'specificationGroups', 'productJsonLd', 'faqJsonLd', 'isWishlisted', 'isComparing',
            'relatedProducts',
        ));
    }
}
