<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Services\Storefront\FilterQueryParser;
use App\Services\Storefront\ProductListingService;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    public function show(Request $request, string $slug, FilterQueryParser $parser, ProductListingService $listing)
    {
        $collection = Collection::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $filters = $parser->fromRequest($request);
        $result = $listing->paginate($collection->products()->getQuery()->published(), $filters);

        return view('storefront.collections.show', compact('collection', 'result', 'filters'));
    }
}