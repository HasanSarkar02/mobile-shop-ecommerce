<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\Product;
use App\Support\ProductFilterState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Single pipeline for every listing surface (Category, Brand, Collection, Search).
 * No page-specific filter/sort logic is ever duplicated — each controller only
 * supplies the base query; everything else runs through here.
 */
class ProductListingService
{
    public function __construct(private readonly FacetResolver $facets) {}

    public function paginate(Builder $query, ProductFilterState $filters): array
    {
        $query = $query->clone();

        // Eager-load everything a product card renders (name/slug, image,
        // cheapest variant, EMI) so the grid never triggers per-card queries.
        $query->with(['translations', 'variants', 'media', 'emiPlans']);

        $this->applyStaticFilters($query, $filters);
        $this->applyAttributeFilters($query, $filters);

        $facets = $this->facets->resolve($query);

        $this->applySort($query, $filters->sort);

        return [
            'products' => $query->paginate($filters->perPage, ['products.*'], 'page', $filters->page)->withQueryString(),
            'facets' => $facets,
        ];
    }

    private function applyStaticFilters(Builder $query, ProductFilterState $filters): void
    {
        if ($filters->brandIds !== []) {
            $query->whereIn('brand_id', $filters->brandIds);
        }

        if ($filters->priceMin !== null || $filters->priceMax !== null) {
            $query->whereHas('variants', function (Builder $v) use ($filters): void {
                $v->where('is_active', true);

                if ($filters->priceMin !== null) {
                    $v->where('price', '>=', $filters->priceMin);
                }

                if ($filters->priceMax !== null) {
                    $v->where('price', '<=', $filters->priceMax);
                }
            });
        }

        if ($filters->inStockOnly) {
            $query->inStock();
        }

        if ($filters->emiOnly) {
            $query->whereHas('emiPlans');
        }

        if ($filters->warrantyOnly) {
            $query->whereHas('translations', fn (Builder $t) => $t->whereNotNull('warranty_info'));
        }

        if ($filters->onSaleOnly) {
            $query->where('max_discount_percentage', '>', 0);
        }

        if ($filters->newArrivalOnly) {
            $query->where('published_at', '>=', now()->subDays((int) config('catalog.new_arrival_days')));
        }

        if ($filters->officialOnly) {
            $query->where('is_official_import', true);
        }

        if ($filters->collectionIds !== []) {
            $query->whereHas('collections', fn (Builder $c) => $c->whereIn('collections.id', $filters->collectionIds));
        }
    }

    private function applyAttributeFilters(Builder $query, ProductFilterState $filters): void
    {
        foreach ($filters->attributes as $code => $values) {
            if ($values === []) {
                continue;
            }

            $query->whereHas('attributeValues', function (Builder $q) use ($code, $values): void {
                $q->whereHas('attributeDefinition', fn (Builder $ad) => $ad->where('code', $code))
                    ->where(function (Builder $sub) use ($values): void {
                        $sub->whereHas('attributeOption', fn (Builder $opt) => $opt->whereIn('value', $values))
                            ->orWhereIn('value_string', $values)
                            ->orWhereIn('value_integer', $values)
                            ->orWhereIn('value_decimal', $values);
                    });
            });
        }
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'newest' => $query->orderByDesc('published_at'),
            'price_low' => $query->orderBy('base_price'),
            'price_high' => $query->orderByDesc('base_price'),
            'most_viewed' => $query->orderByDesc('view_count'),
            'name_asc' => $this->applyNameSort($query),
            'best_selling' => $this->applyBestSellingSort($query),
            default => $query->orderByDesc('is_featured')->orderByDesc('published_at'),
        };
    }

    private function applyNameSort(Builder $query): void
    {
        $query->select('products.*')
            ->join('product_translations as pt_sort', function ($join): void {
                $join->on('pt_sort.product_id', '=', 'products.id')->where('pt_sort.locale', '=', 'en');
            })
            ->orderBy('pt_sort.name');
    }

    private function applyBestSellingSort(Builder $query): void
    {
        // Raw DB::table() bypasses Eloquent's tenant global scope entirely, so
        // tenant_id must be filtered explicitly here. In practice the outer
        // $query (Product, which is tenant-scoped) already guarantees tenant()
        // is resolved by this point — see BelongsToTenant — but this subquery
        // joins order_items/orders directly and must not depend on that
        // indirectly; it states its own tenant isolation explicitly.
        $query->select('products.*')
            ->leftJoinSub(
                DB::table('order_items')
                    ->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->where('orders.tenant_id', tenant()->id)
                    ->whereIn('orders.status', ['confirmed', 'processing', 'shipped', 'delivered'])
                    ->groupBy('product_variants.product_id')
                    ->select('product_variants.product_id as bs_product_id', DB::raw('SUM(order_items.quantity) as bs_total')),
                'bs',
                'bs.bs_product_id',
                '=',
                'products.id',
            )
            ->orderByDesc('bs.bs_total');
    }

    public function bestSelling(int $limit): Collection
    {
        $query = Product::published()->with(['translations', 'variants', 'media', 'emiPlans']);
        $this->applyBestSellingSort($query);

        return $query->limit($limit)->get();
    }
}
