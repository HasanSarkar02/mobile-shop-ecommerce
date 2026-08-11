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
    public function index()
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->withCount(['products' => fn ($query) => $query->published()])
            ->orderBy('name')
            ->get();

        return view('storefront.categories.index', compact('categories'));
    }

        public function show(Request $request, string $slug, FilterQueryParser $parser)

    {

        $category = Category::query()->where('slug', $slug)->firstOrFail();

        $isFiltered = $parser->fromRequest($request)->isFiltered();
        return view('storefront.categories.show', compact('category', 'isFiltered'));

    }


}
