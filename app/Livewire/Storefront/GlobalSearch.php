<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Models\Category;
use App\Models\Product;
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

    /** @var Collection<int, array{ title: string, url: string }> */
    public Collection $posts;

    /** @var Collection<int, Category> */
    public Collection $popularCategories;

    /** @var Collection<int, string> */
    public Collection $trendingSearches;

    public function mount(): void
    {
        $this->products = collect();
        $this->categories = collect();
        $this->brands = collect();
        $this->posts = collect();
        $top = Category::query()->whereNull('parent_id')->orderBy('name')->get(['id', 'name', 'slug']);
        if ($top->isNotEmpty()) {
            $allIds = $top->pluck('id')->all();
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
            $withCounts = $top->map(function (Category $cat) use ($counts) {
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
            $this->popularCategories = $withCounts->take(6);
        } else {
            $this->popularCategories = collect();
        }
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
            $this->posts = collect();

            return;
        }

        $result = app(GlobalSearchService::class)->suggest($value, 5, 3, 3);

        $this->products = $result['products'];
        $this->categories = $result['categories'];
        $this->brands = $result['brands'];
        $this->posts = $result['posts'] ?? collect();
    }

    public function clear(): void
    {
        $this->searchQuery = '';
        $this->products = collect();
        $this->categories = collect();
        $this->brands = collect();
        $this->posts = collect();
    }

    public function render(): View
    {
        return view('livewire.storefront.global-search');
    }
}
