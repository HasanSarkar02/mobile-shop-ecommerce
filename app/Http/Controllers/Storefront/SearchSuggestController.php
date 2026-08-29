<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\GlobalSearchService;
use Illuminate\Http\Request;

class SearchSuggestController extends Controller
{
    public function __invoke(Request $request, GlobalSearchService $search)
    {
        $term = (string) $request->query('q', '');

        $result = $search->suggest($term);

        return response()->json($result);
    }
}
