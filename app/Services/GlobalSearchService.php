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

    private function useDatabaseFallback(): bool
    {
        return config('scout.driver') === 'database';
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    /**
     * Tenant-scoped LIKE search that mirrors Scout's deep intent but hits
     * real columns only. Covers: product_translations (name/slug/description),
     * products.model_number/id, variants SKU/barcode, tags, brands, categories.
     * Used only when SCOUT_DRIVER=database, where Scout's DatabaseEngine would
     * otherwise try `products.name like %term%` and throw 42S22.
     *
     * @return Collection<int, int>
     */
    private function databaseSearchIds(string $term): Collection
    {
        $like = '%'.$this->escapeLike($term).'%';
        $tenantId = (int) tenant()->id;

        return Product::query()
            ->where('products.tenant_id', $tenantId)
            ->where(function ($q) use ($like, $tenantId): void {
                $q->where('products.model_number', 'like', $like)
                    ->orWhere('products.id', 'like', $like)
                    ->orWhereExists(function ($sq) use ($like, $tenantId): void {
                        $sq->selectRaw('1')->from('product_translations')
                            ->whereColumn('product_translations.product_id', 'products.id')
                            ->where('product_translations.tenant_id', $tenantId)
                            ->where(function ($qq) use ($like): void {
                                $qq->where('product_translations.name', 'like', $like)
                                    ->orWhere('product_translations.slug', 'like', $like)
                                    ->orWhere('product_translations.description', 'like', $like);
                            });
                    })
                    ->orWhereExists(function ($sq) use ($like): void {
                        $sq->selectRaw('1')->from('product_variants')
                            ->whereColumn('product_variants.product_id', 'products.id')
                            ->where(function ($qq) use ($like): void {
                                $qq->where('product_variants.sku', 'like', $like)
                                    ->orWhere('product_variants.barcode', 'like', $like);
                            });
                    })
                    ->orWhereExists(function ($sq) use ($like): void {
                        $sq->selectRaw('1')->from('product_tag')
                            ->join('tags', 'tags.id', '=', 'product_tag.tag_id')
                            ->whereColumn('product_tag.product_id', 'products.id')
                            ->where('tags.name', 'like', $like);
                    })
                    ->orWhereExists(function ($sq) use ($like): void {
                        $sq->selectRaw('1')->from('brands')
                            ->whereColumn('brands.id', 'products.brand_id')
                            ->where('brands.name', 'like', $like);
                    })
                    ->orWhereExists(function ($sq) use ($like): void {
                        $sq->selectRaw('1')->from('categories')
                            ->whereColumn('categories.id', 'products.category_id')
                            ->where('categories.name', 'like', $like);
                    });
            })
            ->pluck('products.id');
    }

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

        $productIds = $this->useDatabaseFallback()
            ? $this->databaseSearchIds($term)->take($productLimit)
            : Product::search($term)
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

        $ids = $this->useDatabaseFallback()
            ? $this->databaseSearchIds($term)
            : Product::search($term)
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

        return $this->useDatabaseFallback()
            ? $this->databaseSearchIds($term)
            : Product::search($term)
                ->where('tenant_id', (int) tenant()->id)
                ->keys();
    }
}
