<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;
use App\Models\StaticPage;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function index()
    {
        $xml = Cache::remember('sitemap:'.tenant()->id, 3600, function () {
            $products = Product::published()->with('translations')->get();
            $categories = Category::query()->get();
            $brands = Brand::query()->get();
            $collections = Collection::query()->where('is_active', true)->get();
            $pages = StaticPage::query()->where('status', 'published')->get();
            $posts = BlogPost::query()->where('status', 'published')->get();

            return view('storefront.sitemap', compact('products', 'categories', 'brands', 'collections', 'pages', 'posts'))->render();
        });

        return response($xml, 200)->header('Content-Type', 'text/xml');
    }
}