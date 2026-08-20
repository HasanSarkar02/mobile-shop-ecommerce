<?php

// app/Livewire/ProductCatalog.php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\SearchQuery;
use App\Services\Storefront\ProductCardData;
use App\Services\Storefront\ProductListingService;
use App\Services\WishlistService;
use App\Support\ProductFilterState;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Single Livewire component behind the category, brand, and search listing
 * pages. Mirrors exactly what CategoryController/BrandController/
 * SearchController already did (base query + ProductListingService::paginate()
 * + FilterQueryParser's query-string shape) — the goal here is interaction
 * speed (no full page reload per filter change), not new filtering logic.
 * ProductListingService/ProductFilterState/FacetResolver are untouched.
 */
class ProductCatalog extends Component
{
    use WithPagination;

    public string $mode; // 'category' | 'brand' | 'search'

    public ?string $slug = null;

    public ?string $term = null;

    #[Url(as: 'brand')]
    public array $brandIds = [];

    #[Url(as: 'price_min')]
    public ?string $priceMin = null;

    #[Url(as: 'price_max')]
    public ?string $priceMax = null;

    #[Url(as: 'in_stock')]
    public bool $inStockOnly = false;

    #[Url(as: 'emi')]
    public bool $emiOnly = false;

    #[Url(as: 'warranty')]
    public bool $warrantyOnly = false;

    #[Url(as: 'on_sale')]
    public bool $onSaleOnly = false;

    #[Url(as: 'new_arrival')]
    public bool $newArrivalOnly = false;

    #[Url(as: 'official')]
    public bool $officialOnly = false;

    /** @var array<string, array<string>> attribute code => selected values */
    #[Url]
    public array $attr = [];

    #[Url]
    public string $sort = 'featured';

    public function mount(string $mode, ?string $slug = null, ?string $term = null): void
    {
        $this->mode = $mode;
        $this->slug = $slug;
        $this->term = $term;

        // Logged here, not in render(): mount() runs exactly once per actual
        // search (the initial full page load), whereas render() re-runs on
        // every subsequent filter/sort interaction — logging there would
        // create a new SearchQuery row every time the user toggled a filter
        // on the same search term.
        if ($this->mode === 'search' && $this->term !== '' && $this->term !== null) {
            SearchQuery::query()->create([
                'tenant_id' => tenant()->id,
                'term' => $this->term,
                'results_count' => Product::search($this->term)->keys()->count(),
                'searched_at' => now(),
            ]);
        }
    }

    /** Any filter change should return the user to page 1, not strand them on a now-empty page. */
    public function updated(string $name): void
    {
        if ($name !== 'sort') {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['brandIds', 'priceMin', 'priceMax', 'inStockOnly', 'emiOnly', 'warrantyOnly', 'onSaleOnly', 'newArrivalOnly', 'officialOnly', 'attr']);
        $this->resetPage();
    }

    /**
     * Deliberately distinct from ProductFilterState::isFiltered(), which
     * also treats a non-default sort as "filtered" (correct for its own
     * domain purpose). The chip bar is specifically about removable filter
     * selections — showing it (with no removable chips) just because the
     * user changed sort order would be confusing, and "Clear all" doesn't
     * reset sort, so it must not be what decides the bar's visibility.
     */
    public function hasActiveFilters(): bool
    {
        return $this->brandIds !== []
            || $this->priceMin !== null
            || $this->priceMax !== null
            || $this->inStockOnly
            || $this->emiOnly
            || $this->warrantyOnly
            || $this->onSaleOnly
            || $this->newArrivalOnly
            || $this->officialOnly
            || array_filter($this->attr) !== [];
    }

    public function render(ProductListingService $listing, ProductCardData $cards, WishlistService $wishlists)
    {
        $baseQuery = $this->resolveBaseQuery();
        $filters = $this->buildFilterState();
        $result = $listing->paginate($baseQuery, $filters);

        $wishlistedProductIds = $wishlists->wishlistedProductIds($result['products']->pluck('id'));

        return view('livewire.product-catalog', [
            'result' => $result,
            'cards' => $cards->forMany($result['products'], $wishlistedProductIds),
            'wishlistedProductIds' => $wishlistedProductIds,
        ]);
    }

    private function resolveBaseQuery(): Builder
    {
        return match ($this->mode) {
            'category' => $this->categoryWithDescendantsQuery(),
            'brand' => Brand::query()->where('slug', $this->slug)->firstOrFail()->products()->getQuery()->published(),
            'collection' => Collection::query()->where('slug', $this->slug)->where('is_active', true)->firstOrFail()->products()->getQuery()->published(),
            'search' => $this->searchBaseQuery(),
        };
    }

    /**
     * Category listing includes products from the selected category AND all
     * of its descendants (recursively). Category trees use a self-referencing
     * parent_id (parent → children), product category_id points at exactly
     * one category, so we gather every descendant id up front and filter the
     * product query against that set — no duplicate products, no schema changes.
     */
    private function categoryWithDescendantsQuery(): Builder
    {
        $category = Category::query()->where('slug', $this->slug)->firstOrFail();

        $ids = [$category->id];
        $pending = [$category->id];

        while ($pending !== []) {
            $children = Category::query()
                ->whereIn('parent_id', $pending)
                ->pluck('id')
                ->all();

            if ($children === []) {
                break;
            }

            $ids = array_values(array_unique(array_merge($ids, $children)));
            $pending = $children;
        }

        return Product::query()
            ->whereIn('category_id', $ids)
            ->published();
    }

    private function searchBaseQuery(): Builder
    {
        $query = Product::query()->published();

        if ($this->term !== '' && $this->term !== null) {
            $ids = Product::search($this->term)->keys();
            $query->whereIn('id', $ids);
        }

        return $query;
    }

    private function buildFilterState(): ProductFilterState
    {
        return new ProductFilterState(
            brandIds: array_map('intval', $this->brandIds),
            priceMin: $this->priceMin !== null && $this->priceMin !== '' ? (int) round(((float) $this->priceMin) * 100) : null,
            priceMax: $this->priceMax !== null && $this->priceMax !== '' ? (int) round(((float) $this->priceMax) * 100) : null,
            inStockOnly: $this->inStockOnly,
            emiOnly: $this->emiOnly,
            warrantyOnly: $this->warrantyOnly,
            onSaleOnly: $this->onSaleOnly,
            newArrivalOnly: $this->newArrivalOnly,
            officialOnly: $this->officialOnly,
            attributes: array_filter($this->attr),
            sort: $this->sort,
            page: $this->getPage(),
            searchTerm: $this->term,
        );
    }
}
