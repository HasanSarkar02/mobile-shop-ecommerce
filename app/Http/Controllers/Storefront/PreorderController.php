<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Storefront\ProductCardData;
use App\Services\Storefront\ProductListingService;
use App\Services\WishlistService;
use App\Support\ProductFilterState;
use Illuminate\View\View;

class PreorderController extends Controller
{
    public function index(ProductListingService $listings, ProductFilterState $filters, ProductCardData $cards, WishlistService $wishlists): View
    {
        $filters = $filters->withPage((int) request()->query('page', 1));

        $base = Product::published()->whereHas('variants', function ($q): void {
            $q->where('is_active', true)->where('fulfillment_strategy', 'preorder');
        });

        $result = $listings->paginate($base, $filters);

        $wishlistedIds = $wishlists->wishlistedProductIds($result['products']->pluck('id'));

        return view('storefront.preorders.index', [
            'products' => $result['products'],
            'cards' => $cards->forMany($result['products'], $wishlistedIds),
            'facets' => $result['facets'],
            'filters' => $filters,
        ]);
    }
}
