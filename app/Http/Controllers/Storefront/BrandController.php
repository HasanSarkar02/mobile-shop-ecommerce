<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\Storefront\FilterQueryParser;
use App\Services\Storefront\ProductListingService;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function show(Request $request, string $slug, FilterQueryParser $parser, ProductListingService $listing)
    {
        $brand = Brand::query()->where('slug', $slug)->firstOrFail();
        $filters = $parser->fromRequest($request);
        $result = $listing->paginate($brand->products()->getQuery()->published(), $filters);

        return view('storefront.brands.show', compact('brand', 'result', 'filters'));
    }
}