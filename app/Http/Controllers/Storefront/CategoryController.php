<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Storefront\FilterQueryParser;
use App\Services\Storefront\ProductListingService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function show(Request $request, string $slug, FilterQueryParser $parser, ProductListingService $listing)
    {
        $category = Category::query()->where('slug', $slug)->firstOrFail();
        $filters = $parser->fromRequest($request);
        $result = $listing->paginate($category->products()->getQuery()->published(), $filters);

        return view('storefront.categories.show', compact('category', 'result', 'filters'));
    }
}