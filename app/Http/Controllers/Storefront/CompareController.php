<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\CompareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompareController extends Controller
{
    public function show(CompareService $compare)
    {
        $products = Product::query()
            ->whereIn('id', $compare->ids())
            ->with(['translations', 'brand', 'variants', 'attributeValues.attributeDefinition', 'attributeValues.attributeOption'])
            ->get();

        return view('storefront.compare.show', compact('products'));
    }

    public function toggle(Request $request, CompareService $compare): RedirectResponse
    {
        try {
            $compare->toggle((int) $request->input('product_id'));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back();
    }
}