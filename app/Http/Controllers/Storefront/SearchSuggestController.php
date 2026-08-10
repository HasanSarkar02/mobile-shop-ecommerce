<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class SearchSuggestController extends Controller
{
    public function __invoke(Request $request)
    {
        $term = (string) $request->query('q', '');

        if (mb_strlen($term) < 2) {
            return response()->json(['products' => [], 'categories' => [], 'brands' => []]);
        }

        $productIds = Product::search($term)->keys()->take(6);
        $products = Product::query()->published()->whereIn('id', $productIds)->with('translations')->get()
            ->map(fn (Product $p) => [
                'name' => $p->name,
                'url' => route('storefront.product', $p->translation('en')?->slug),
                'thumb' => $p->getFirstMediaUrl('images', 'thumb'),
                'price' => $p->base_price / 100,
            ]);

        $categories = Category::query()->where('name', 'like', "%{$term}%")->limit(4)
            ->get(['name', 'slug'])
            ->map(fn (Category $c) => ['name' => $c->name, 'url' => route('storefront.category', $c->slug)]);

        $brands = Brand::query()->where('name', 'like', "%{$term}%")->limit(4)
            ->get(['name', 'slug'])
            ->map(fn (Brand $b) => ['name' => $b->name, 'url' => route('storefront.brand', $b->slug)]);

        return response()->json(compact('products', 'categories', 'brands'));
    }
}