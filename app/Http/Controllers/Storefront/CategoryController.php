<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;

class CategoryController extends Controller
{
    public function show(string $slug)
    {
        $category = Category::query()->where('slug', $slug)->firstOrFail();

        $products = $category->products()
            ->published()
            ->with(['translations', 'variants', 'media'])
            ->paginate(24);

        return view('storefront.categories.show', compact('category', 'products'));
    }
}