<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Product;
use App\Services\Storefront\FilterQueryParser;
use App\Support\Seo\SeoData;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        if ($categories->isNotEmpty()) {
            $allIds = $categories->pluck('id')->all();
            /** @var array<int, int> $pending */
            $pending = $allIds;
            while ($pending !== []) {
                $children = Category::query()->whereIn('parent_id', $pending)->pluck('id')->all();
                if ($children === []) {
                    break;
                }
                $allIds = array_merge($allIds, $children);
                $pending = $children;
            }
            $allIds = array_values(array_unique($allIds));

            $counts = Product::published()
                ->whereIn('category_id', $allIds)
                ->selectRaw('category_id, COUNT(*) as cnt')
                ->groupBy('category_id')
                ->pluck('cnt', 'category_id')
                ->map(fn ($v) => (int) $v);

            /** @var Category $cat */
            foreach ($categories as $cat) {
                $ids = $cat->descendantIds();
                $total = 0;
                foreach ($ids as $id) {
                    $total += (int) ($counts[$id] ?? 0);
                }
                $cat->setAttribute('products_count', $total);
            }
        }

        return view('storefront.categories.index', compact('categories'));
    }

    public function show(Request $request, string $slug, FilterQueryParser $parser)
    {

        $category = Category::query()->where('slug', $slug)->firstOrFail();

        $isFiltered = $parser->fromRequest($request)->isFiltered();

        $seo = SeoData::fromCategory($category, $isFiltered);

        $relatedBlogPosts = BlogPost::query()
            ->where('status', 'published')
            ->where('published_at', '<=', now())
            ->where(function ($q) use ($category) {
                $q->where('title', 'like', '%'.$category->name.'%')
                    ->orWhere('excerpt', 'like', '%'.$category->name.'%')
                    ->orWhere('content', 'like', '%'.$category->name.'%');
            })
            ->latest('published_at')
            ->limit(3)
            ->get();
        if ($relatedBlogPosts->isEmpty()) {
            $relatedBlogPosts = BlogPost::query()->where('status', 'published')->where('published_at', '<=', now())->latest('published_at')->limit(3)->get();
        }

        return view('storefront.categories.show', compact('category', 'isFiltered', 'seo', 'relatedBlogPosts'));

    }
}
