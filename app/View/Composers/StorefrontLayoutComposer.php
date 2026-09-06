<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\StaticPage;
use App\Services\WishlistService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Supplies the data every storefront page needs for its chrome (header nav,
 * announcement bar, footer). Bound to the layout view in AppServiceProvider
 * so no controller has to remember to pass this, and so layout.blade.php
 * doesn't run queries directly.
 */
class StorefrontLayoutComposer
{
    public function __construct(private readonly WishlistService $wishlists) {}

    public function compose(View $view): void
    {
        $view->with([
            'headerMenu' => Menu::query()->where('location', 'header')->with('topLevelItems.children')->first(),
            'footerMenu' => Menu::query()->where('location', 'footer')->with('topLevelItems')->first(),
            'announcement' => Announcement::query()->currentlyActive()->first(),
            'footerPages' => StaticPage::query()
                ->where('show_in_footer', true)
                ->where('status', 'published')
                ->get()
                ->groupBy('footer_group'),
            'hasOutlets' => Outlet::query()->where('is_active', true)->exists(),
            'hasPreorders' => $this->hasPreorders(),
            'theme' => tenant()->themeSettings,
            'wishlistCount' => $this->wishlists->wishlistCount(),
            'headerCategories' => $this->headerCategories(),
        ]);
    }

    private function hasPreorders(): bool
    {
        $tenant = tenant();

        if ($tenant === null) {
            return false;
        }

        return Cache::remember(
            "tenant:{$tenant->id}:has_preorders",
            3600,
            fn (): bool => Product::published()
                ->whereHas('variants', fn ($q) => $q->where('is_active', true)->where('fulfillment_strategy', 'preorder'))
                ->exists(),
        );
    }

    public static function forgetHasPreordersCache(int $tenantId): void
    {
        Cache::forget("tenant:{$tenantId}:has_preorders");
    }

    /**
     * Top-level categories with inclusive published product counts (self + all descendants).
     */
    private function headerCategories(): Collection
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        if ($categories->isEmpty()) {
            return $categories;
        }

        // Gather all descendant IDs for every top-level + child in one go to avoid N+1.
        // For shallow trees (current depth ≤2) this is 2 queries, not per-category.
        $allIds = $categories->pluck('id')->all();
        foreach ($categories as $cat) {
            $allIds = array_merge($allIds, $cat->children->pluck('id')->all());
        }
        // Include grandchildren for true recursive inclusive counts (depth >1)
        /** @var array<int, int> $pending */
        $pending = collect($allIds)->unique()->values()->all();
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

        /** @var Category $cat */
        foreach ($categories as $cat) {
            $ids = $cat->descendantIds();
            $total = 0;
            foreach ($ids as $id) {
                $total += (int) ($counts[$id] ?? 0);
            }
            $cat->setAttribute('products_count', $total);

            /** @var Category $child */
            foreach ($cat->children as $child) {
                $childIds = $child->descendantIds();
                $childTotal = 0;
                foreach ($childIds as $cid) {
                    $childTotal += (int) ($counts[$cid] ?? 0);
                }
                $child->setAttribute('products_count', $childTotal);
            }
        }

        return $categories;
    }
}
