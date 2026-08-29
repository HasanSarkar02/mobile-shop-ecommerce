<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\Tenancy\TenantUrlGenerator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class GlobalSearchService
{
    public function __construct(private readonly TenantUrlGenerator $urls) {}

    /**
     * Lightweight suggest for autocomplete — products (via Scout, deep: name,
     * slug, model_number, tags, SKU/barcode, brand, category) plus matching
     * categories and brands by name.
     *
     * @return array{products: Collection, categories: Collection, brands: Collection}
     */
    public function suggest(string $term, int $productLimit = 6, int $categoryLimit = 4, int $brandLimit = 4): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [
                'products' => collect(),
                'categories' => collect(),
                'brands' => collect(),
            ];
        }

        $productIds = Product::search($term)
            ->where('tenant_id', (int) tenant()->id)
            ->keys()
            ->take($productLimit);

        $productModels = Product::query()->published()->whereIn('id', $productIds)->with('translations')->get();
        $products = collect();
        foreach ($productModels as $p) {
            $products->push([
                'name' => $p->name,
                'url' => $this->urls->canonicalRoute(tenant(), 'storefront.product', [($p->translation() ?? $p->translation('en'))?->slug]),
                'thumb' => $p->getFirstMediaUrl('images', 'thumb'),
                'price' => $p->base_price / 100,
            ]);
        }

        $categoryModels = Category::query()->where('name', 'like', "%{$term}%")->limit($categoryLimit)->get(['name', 'slug']);
        $categories = collect();
        foreach ($categoryModels as $c) {
            $categories->push(['name' => $c->name, 'url' => $this->urls->canonicalRoute(tenant(), 'storefront.category', [$c->slug])]);
        }

        $brandModels = Brand::query()->where('name', 'like', "%{$term}%")->limit($brandLimit)->get(['name', 'slug']);
        $brands = collect();
        foreach ($brandModels as $b) {
            $brands->push(['name' => $b->name, 'url' => $this->urls->canonicalRoute(tenant(), 'storefront.brand', [$b->slug])]);
        }

        return compact('products', 'categories', 'brands');
    }

    /**
     * Full search — Scout for relevance, then tenant-scoped Eloquent paginator.
     * Caller can pass through ProductFilterState for faceting if needed, but
     * simplest is pure Scout ids + published scope.
     */
    public function search(string $term, int $perPage = 24, int $page = 1): LengthAwarePaginator|EloquentCollection
    {
        $term = trim($term);

        if ($term === '') {
            return Product::query()->published()->whereRaw('1=0')->paginate($perPage, ['*'], 'page', $page);
        }

        $ids = Product::search($term)
            ->where('tenant_id', (int) tenant()->id)
            ->keys();

        if ($ids->isEmpty()) {
            return Product::query()->published()->whereRaw('1=0')->paginate($perPage, ['*'], 'page', $page);
        }

        return Product::query()->published()->whereIn('id', $ids)->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Raw product models for callers that need Eloquent directly (e.g. ProductCatalog search base query).
     *
     * @return Collection<int, int>
     */
    public function searchIds(string $term): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return collect();
        }

        return Product::search($term)
            ->where('tenant_id', (int) tenant()->id)
            ->keys();
    }
}
