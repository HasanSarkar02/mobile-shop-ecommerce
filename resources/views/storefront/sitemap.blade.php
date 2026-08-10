<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ route('storefront.home') }}</loc>
    </url>
    @foreach ($products as $product)
        @if ($slug = $product->translation('en')?->slug)
            <url>
                <loc>{{ route('storefront.product', $slug) }}</loc>
                <lastmod>{{ $product->updated_at->toAtomString() }}</lastmod>
            </url>
        @endif
    @endforeach
    @foreach ($categories as $category)
        <url>
            <loc>{{ route('storefront.category', $category->slug) }}</loc>
        </url>
    @endforeach
    @foreach ($brands as $brand)
        <url>
            <loc>{{ route('storefront.brand', $brand->slug) }}</loc>
        </url>
    @endforeach
    @foreach ($collections as $collection)
        <url>
            <loc>{{ route('storefront.collection', $collection->slug) }}</loc>
        </url>
    @endforeach
    @foreach ($pages as $page)
        <url>
            <loc>{{ url('/page/' . $page->slug) }}</loc>
        </url>
    @endforeach
    @foreach ($posts as $post)
        <url>
            <loc>{{ route('storefront.blog.show', $post->slug) }}</loc>
            <lastmod>{{ $post->updated_at->toAtomString() }}</lastmod>
        </url>
    @endforeach
</urlset>
