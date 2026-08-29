<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Models\Category;
use App\Models\SearchQuery;
use App\Services\GlobalSearchService;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $searchQuery = '';

    /** @var Collection<int, array{ name: string, url: string, thumb: string, price: float }> */
    public Collection $products;

    /** @var Collection<int, array{ name: string, url: string }> */
    public Collection $categories;

    /** @var Collection<int, array{ name: string, url: string }> */
    public Collection $brands;

    /** @var Collection<int, Category> */
    public Collection $popularCategories;

    /** @var Collection<int, string> */
    public Collection $trendingSearches;

    public function mount(): void
    {
        $this->products = collect();
        $this->categories = collect();
        $this->brands = collect();
        $this->popularCategories = Category::query()
            ->withCount('products')
            ->orderByDesc('products_count')
            ->limit(6)
            ->get(['id', 'name', 'slug']);
        $this->trendingSearches = SearchQuery::query()
            ->selectRaw('term, COUNT(*) as cnt')
            ->groupBy('term')
            ->orderByDesc('cnt')
            ->limit(6)
            ->pluck('term');
    }

    public function updatedSearchQuery(string $value): void
    {
        $value = trim($value);

        if (mb_strlen($value) < 2) {
            $this->products = collect();
            $this->categories = collect();
            $this->brands = collect();

            return;
        }

        $result = app(GlobalSearchService::class)->suggest($value, 5, 3, 3);

        $this->products = $result['products'];
        $this->categories = $result['categories'];
        $this->brands = $result['brands'];
    }

    public function clear(): void
    {
        $this->searchQuery = '';
        $this->products = collect();
        $this->categories = collect();
        $this->brands = collect();
    }

    public function render(): View
    {
        return view('livewire.storefront.global-search');
    }
}
