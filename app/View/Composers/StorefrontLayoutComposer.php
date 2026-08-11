<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Menu;
use App\Models\StaticPage;
use App\Models\WishlistItem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Supplies the data every storefront page needs for its chrome (header nav,
 * announcement bar, footer). Bound to the layout view in AppServiceProvider
 * so no controller has to remember to pass this, and so layout.blade.php
 * doesn't run queries directly.
 */
class StorefrontLayoutComposer
{
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
            'wishlistCount' => $this->wishlistCount(),
            'headerCategories' => Category::query()
                ->whereNull('parent_id')
                ->with(['children' => fn ($query) => $query->orderBy('name')])
                ->withCount(['products' => fn ($query) => $query->published()])
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Deliberately does NOT call WishlistService::getOrCreateWishlist() —
     * that creates a Wishlist row as a side effect, which would happen on
     * every single page view (this composer runs on every storefront page)
     * for every anonymous visitor who has never touched the wishlist
     * feature. Only counts an existing wishlist; never creates one.
     */
    private function wishlistCount(): int
    {
        if ($customer = Auth::guard('customer')->user()) {
            return WishlistItem::query()
                ->whereHas('wishlist', fn ($q) => $q->where('customer_id', $customer->id)->where('is_default', true))
                ->count();
        }

        if ($token = request()->cookie('wishlist_token')) {
            return WishlistItem::query()
                ->whereHas('wishlist', fn ($q) => $q->where('guest_token', $token)->where('is_default', true))
                ->count();
        }

        return 0;
    }
}