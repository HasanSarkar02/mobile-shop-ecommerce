<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Storefront\ProductListingService;
use App\Support\ProductFilterState;

class PreorderController extends Controller
{
    public function index(ProductListingService $listings, ProductFilterState $filters)
    {
        $filters->page = (int) request()->query('page', 1);

        $base = Product::published()->whereHas('variants', function ($q): void {
            $q->where('is_active', true)->where('fulfillment_strategy', 'preorder');
        });

        $result = $listings->paginate($base, $filters);

        return view('storefront.preorders.index', [
            'products' => $result['products'],
            'facets' => $result['facets'],
            'filters' => $filters,
        ]);
    }
}
