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
    public function index()
    {
        $brands = Brand::query()
            ->withCount(['products' => fn ($query) => $query->published()])
            ->orderBy('name')
            ->get();

        return view('storefront.brands.index', compact('brands'));
    }

    public function show(Request $request, string $slug, FilterQueryParser $parser)

    {
        $brand = Brand::query()->where('slug', $slug)->firstOrFail();
        $isFiltered = $parser->fromRequest($request)->isFiltered();
        return view('storefront.brands.show', compact('brand', 'isFiltered'));

    }

}
