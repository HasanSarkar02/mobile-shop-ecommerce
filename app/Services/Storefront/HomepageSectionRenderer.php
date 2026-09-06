<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Collection;
use App\Models\HomepageSection;
use App\Models\Product;
use Illuminate\Support\Collection as EloquentCollection;

class HomepageSectionRenderer
{
    public function __construct(private readonly ProductListingService $listing) {}

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
            'campaign' => $this->resolveCampaignProducts($section, $limit),
            default => $query->where('is_featured', true)->limit($limit)->get(),
        };
    }

    /**
     * Campaign-sourced grid: only an eligible campaign's published attached
     * products, in pivot order — same rule the offer pages enforce via
     * Campaign::scopeEligible.
     */
    private function resolveCampaignProducts(HomepageSection $section, int $limit): EloquentCollection
    {
        $campaign = Campaign::query()
            ->whereKey($section->campaign_id ?? 0)
            ->eligible()
            ->first();

        if ($campaign === null) {
            return new EloquentCollection;
        }

        return Product::query()
            ->published()
            ->with(['translations', 'variants', 'media', 'emiPlans'])
            ->join('campaign_product', 'campaign_product.product_id', '=', 'products.id')
            ->where('campaign_product.campaign_id', $campaign->getKey())
            ->orderBy('campaign_product.sort_order')
            ->select('products.*')
            ->limit($limit)
            ->get();
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

        $candidates = Category::query()->whereNull('parent_id')->get();

        if ($candidates->isEmpty()) {
            return $candidates;
        }

        $allIds = $candidates->pluck('id')->all();
        /** @var array<int, int> $pending */
        $pending = $allIds;
        while ($pending !== []) {
            $children = Category::query()->whereIn('parent_id', $pending)->pluck('id')->all();
            if ($children === []) {
                break;
            }
            $allIds = array_merge($allIds, $children);
            $pending = $children;
        }
        $allIds = array_values(array_unique($allIds));

        $counts = Product::published()
            ->whereIn('category_id', $allIds)
            ->selectRaw('category_id, COUNT(*) as cnt')
            ->groupBy('category_id')
            ->pluck('cnt', 'category_id')
            ->map(fn ($v) => (int) $v);

        $withCounts = $candidates->map(function (Category $cat) use ($counts) {
            $ids = $cat->descendantIds();
            $total = 0;
            foreach ($ids as $id) {
                $total += (int) ($counts[$id] ?? 0);
            }
            $cat->setAttribute('products_count', $total);

            return $cat;
        })->filter(fn (Category $cat) => $cat->products_count > 0)
            ->sortByDesc('products_count')
            ->values();

        return $withCounts->take($limit);
    }

    public function resolveBlogPosts(HomepageSection $section): EloquentCollection
    {
        $config = $section->config ?? [];
        $limit = (int) ($config['limit'] ?? 3);

        return BlogPost::query()
            ->where('status', 'published')
            ->where('published_at', '<=', now())
            ->latest('published_at')
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
