<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Menu;
use App\Models\StaticPage;
use App\Services\WishlistService;
use Illuminate\Contracts\View\View;

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
            'theme' => tenant()->themeSettings,
            'wishlistCount' => $this->wishlists->wishlistCount(),
            'headerCategories' => Category::query()
                ->whereNull('parent_id')
                ->with(['children' => fn ($query) => $query->orderBy('name')])
                ->withCount(['products' => fn ($query) => $query->published()])
                ->orderBy('name')
                ->get(),
        ]);
    }
}
