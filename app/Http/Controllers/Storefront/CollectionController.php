<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Services\Storefront\FilterQueryParser;
use Illuminate\Http\Request;

class CollectionController extends Controller
{
    /** See CategoryController::show() — same reasoning applies here. */
    public function show(Request $request, string $slug, FilterQueryParser $parser)
    {
        $collection = Collection::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $isFiltered = $parser->fromRequest($request)->isFiltered();

        return view('storefront.collections.show', compact('collection', 'isFiltered'));
    }
}