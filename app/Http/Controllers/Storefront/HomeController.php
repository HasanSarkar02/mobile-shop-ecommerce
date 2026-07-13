<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;

class HomeController extends Controller
{
    public function __invoke()
    {
        $featuredProducts = Product::published()
            ->where('is_featured', true)
            ->with(['translations', 'variants', 'media'])
            ->latest()
            ->take(8)
            ->get();

        $categories = Category::query()->whereNull('parent_id')->withCount('products')->get();

        return view('storefront.home', compact('featuredProducts', 'categories'));
    }
}