<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SearchQuery;
use App\Services\Storefront\FilterQueryParser;
use App\Services\Storefront\ProductListingService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request, FilterQueryParser $parser, ProductListingService $listing)
    {
        $term = (string) $request->query('q', '');
        $filters = $parser->fromRequest($request);

        $base = Product::query()->published();

        if ($term !== '') {
            $ids = Product::search($term)->keys();
            $base->whereIn('id', $ids);
        }

        $result = $listing->paginate($base, $filters);

        if ($term !== '') {
            SearchQuery::query()->create([
                'tenant_id' => tenant()->id,
                'term' => $term,
                'results_count' => $result['products']->total(),
                'searched_at' => now(),
            ]);
        }

        return view('storefront.search.index', compact('term', 'result', 'filters'));
    }
}