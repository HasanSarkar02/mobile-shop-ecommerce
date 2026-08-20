<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ $urls->canonicalRoute($tenant, 'storefront.home') }}</loc>
    </url>
    @foreach ($products as $product)
        @if ($slug = $product->translation('en')?->slug)
            <url>
                <loc>{{ $urls->canonicalRoute($tenant, 'storefront.product', [$slug]) }}</loc>
                <lastmod>{{ $product->updated_at->toAtomString() }}</lastmod>
            </url>
        @endif
    @endforeach
    @foreach ($categories as $category)
        <url>
            <loc>{{ $urls->canonicalRoute($tenant, 'storefront.category', [$category->slug]) }}</loc>
        </url>
    @endforeach
    @foreach ($brands as $brand)
        <url>
            <loc>{{ $urls->canonicalRoute($tenant, 'storefront.brand', [$brand->slug]) }}</loc>
        </url>
    @endforeach
    @foreach ($collections as $collection)
        <url>
            <loc>{{ $urls->canonicalRoute($tenant, 'storefront.collection', [$collection->slug]) }}</loc>
        </url>
    @endforeach
    @foreach ($pages as $page)
        <url>
            <loc>{{ $urls->canonicalRoute($tenant, 'storefront.page', [$page->slug]) }}</loc>
        </url>
    @endforeach
    @foreach ($posts as $post)
        <url>
            <loc>{{ $urls->canonicalRoute($tenant, 'storefront.blog.show', [$post->slug]) }}</loc>
            <lastmod>{{ $post->updated_at->toAtomString() }}</lastmod>
        </url>
    @endforeach
</urlset>
