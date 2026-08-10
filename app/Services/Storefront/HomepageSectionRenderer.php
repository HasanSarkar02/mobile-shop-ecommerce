<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\HomepageSection;
use App\Models\Product;
use Illuminate\Support\Collection as EloquentCollection;

class HomepageSectionRenderer
{
    public function __construct(private readonly ProductListingService $listing)
    {
    }

    public function resolveProducts(HomepageSection $section): EloquentCollection
    {
        $config = $section->config ?? [];
        $limit = (int) ($config['limit'] ?? 8);
        $query = Product::published()->with(['translations', 'variants', 'media', 'emiPlans']);

        return match ($config['data_source'] ?? null) {
            'featured' => $query->where('is_featured', true)->orderByDesc('published_at')->limit($limit)->get(),
            'latest' => $query->orderByDesc('published_at')->limit($limit)->get(),
            'best_selling' => $this->listing->bestSelling($limit),
            'category' => $query->where('category_id', $config['category_id'] ?? 0)->limit($limit)->get(),
            'collection' => Collection::query()->find($config['collection_id'] ?? 0)?->products()->published()->limit($limit)->get() ?? collect(),
            'tag' => $query->whereHas('tags', fn ($q) => $q->where('tags.id', $config['tag_id'] ?? 0))->limit($limit)->get(),
            default => $query->where('is_featured', true)->limit($limit)->get(),
        };
    }

    /**
     * Backs the category_grid section type. config['category_ids'] lets an
     * admin hand-pick and order specific categories; with no explicit
     * selection, defaults to the top-level categories with the most
     * published products, so the section looks reasonable with zero
     * configuration rather than rendering empty until an admin sets it up.
     */
    public function resolveCategories(HomepageSection $section): EloquentCollection
    {
        $config = $section->config ?? [];
        $limit = (int) ($config['limit'] ?? 8);

        if (! empty($config['category_ids'])) {
            return Category::query()->whereIn('id', $config['category_ids'])->get()
                ->sortBy(fn ($category) => array_search($category->id, $config['category_ids']))
                ->values();
        }

        return Category::query()
            ->whereNull('parent_id')
            ->withCount(['products' => fn ($q) => $q->published()])
            ->having('products_count', '>', 0)
            ->orderByDesc('products_count')
            ->limit($limit)
            ->get();
    }

    /** Backs the category_grid section type when config['source'] === 'brand'. */
    public function resolveBrands(HomepageSection $section): EloquentCollection
    {
        $config = $section->config ?? [];
        $limit = (int) ($config['limit'] ?? 8);

        if (! empty($config['brand_ids'])) {
            return Brand::query()->whereIn('id', $config['brand_ids'])->get()
                ->sortBy(fn ($brand) => array_search($brand->id, $config['brand_ids']))
                ->values();
        }

        return Brand::query()
            ->withCount(['products' => fn ($q) => $q->published()])
            ->having('products_count', '>', 0)
            ->orderByDesc('products_count')
            ->limit($limit)
            ->get();
    }
}