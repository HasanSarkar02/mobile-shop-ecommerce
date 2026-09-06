<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Models\BlogPost;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Support\Tenancy\TenantUrlGenerator;
use Illuminate\Support\Str;

final readonly class SeoData
{
    public function __construct(
        public string $title,
        public ?string $description,
        public string $canonical,
        public string $image,
        public string $imageAlt,
        public string $type,
        public string $siteName,
        public ?string $robots = null,
        public string $locale = 'en',
    ) {}

    public static function fromProduct(Product $product, ?TenantUrlGenerator $urls = null): self
    {
        $urls ??= app(TenantUrlGenerator::class);
        $tenant = tenant();
        if (! $tenant instanceof Tenant) {
            $related = $product->tenant()->first();
            $tenant = $related instanceof Tenant ? $related : null;
        }
        $t = $product->translation() ?? $product->translation('en');

        $baseTitle = null;
        if ($t !== null) {
            $baseTitle = $t->meta_title ?: $t->name;
        }
        $siteName = $tenant instanceof Tenant ? $tenant->name : (string) config('app.name', 'Store');
        $title = $baseTitle ? $baseTitle.' - '.$siteName : $siteName;

        $description = $t !== null ? $t->meta_description : null;
        if (! filled($description) && $t !== null && filled($t->description)) {
            $description = Str::limit(strip_tags($t->description), 155, '');
        }
        if (! filled($description) && $tenant instanceof Tenant) {
            $settingsDesc = $tenant->settings?->meta_description_default;
            $themeDesc = $tenant->themeSettings?->footer_text;
            $description = filled($settingsDesc) ? $settingsDesc : $themeDesc;
        }
        $description = filled($description) ? trim((string) $description) : null;
        if ($description !== null) {
            $description = Str::limit($description, 155, '');
        }

        $image = $product->getFirstMediaUrl('images', 'large');
        if (! filled($image)) {
            $firstMedia = $product->getMedia('images')->first();
            $image = $firstMedia ? $firstMedia->getUrl('large') : null;
        }
        if (! filled($image)) {
            $firstVariant = $product->variants->first();
            if ($firstVariant) {
                $image = $firstVariant->getFirstMediaUrl('images', 'large');
                if (! filled($image)) {
                    $firstVariantMedia = $firstVariant->getMedia('images')->first();
                    $image = $firstVariantMedia ? $firstVariantMedia->getUrl('large') : null;
                }
            }
        }
        $productName = $t !== null && filled($t->name) ? $t->name : ($product->name ?? $siteName);
        $imageAlt = (string) $productName;
        if (filled($image)) {
            $firstMediaForAlt = $product->getMedia('images')->first();
            if ($firstMediaForAlt) {
                $imageAlt = media_alt($firstMediaForAlt, $imageAlt);
            }
        }

        if (! filled($image) && $tenant instanceof Tenant) {
            $logoPath = $tenant->themeSettings?->logo_path;
            if (filled($logoPath)) {
                $image = '/storage/'.ltrim((string) $logoPath, '/');
            }
        }
        if (! filled($image) && $tenant instanceof Tenant) {
            $faviconPath = $tenant->themeSettings?->favicon_path;
            if (filled($faviconPath)) {
                $image = '/storage/'.ltrim((string) $faviconPath, '/');
            } else {
                $image = '/storage/placeholder-og.png';
            }
        }
        if (! filled($image)) {
            $image = '/storage/placeholder-og.png';
        }

        $image = self::ensureAbsolute((string) $image, $tenant, $urls);
        if (! str_starts_with($image, 'http')) {
            $image = $tenant instanceof Tenant ? $urls->canonicalPath($tenant, $image) : url($image);
        }

        if ($t !== null && filled($t->slug) && $tenant instanceof Tenant) {
            $canonical = $urls->canonicalRoute($tenant, 'storefront.product', [$t->slug]);
        } else {
            $canonical = $tenant instanceof Tenant
                ? $urls->canonicalPath($tenant, request()->getPathInfo() ?: '/')
                : url(request()->getPathInfo() ?: '/');
        }

        $locale = $tenant instanceof Tenant ? $tenant->preferredLocale() : (string) app()->getLocale();

        return new self(
            title: $title,
            description: $description,
            canonical: $canonical,
            image: $image,
            imageAlt: $imageAlt,
            type: 'product',
            siteName: $siteName,
            robots: null,
            locale: $locale,
        );
    }

    public static function fromCategory(Category $category, bool $isFiltered = false, ?TenantUrlGenerator $urls = null): self
    {
        $urls ??= app(TenantUrlGenerator::class);
        $tenant = tenant();
        if (! $tenant instanceof Tenant) {
            $related = $category->tenant()->first();
            $tenant = $related instanceof Tenant ? $related : null;
        }

        $siteName = $tenant instanceof Tenant ? $tenant->name : (string) config('app.name', 'Store');
        $baseTitle = $category->meta_title ?: $category->name;
        $title = $baseTitle ? $baseTitle.' - '.$siteName : $siteName;

        $description = $category->meta_description;
        if (! filled($description) && filled($category->description)) {
            $description = Str::limit(strip_tags($category->description), 155, '');
        }
        if (! filled($description) && $tenant instanceof Tenant) {
            $settingsDesc = $tenant->settings?->meta_description_default;
            $description = filled($settingsDesc) ? $settingsDesc : null;
        }
        $description = filled($description) ? trim((string) $description) : null;
        if ($description !== null) {
            $description = Str::limit($description, 155, '');
        }

        $image = null;
        if (filled($category->image_path)) {
            $image = '/storage/'.ltrim((string) $category->image_path, '/');
        }
        if (! filled($image) && $tenant instanceof Tenant) {
            $logoPath = $tenant->themeSettings?->logo_path;
            if (filled($logoPath)) {
                $image = '/storage/'.ltrim((string) $logoPath, '/');
            } else {
                $faviconPath = $tenant->themeSettings?->favicon_path;
                $image = filled($faviconPath) ? '/storage/'.ltrim((string) $faviconPath, '/') : '/storage/placeholder-og.png';
            }
        }
        if (! filled($image)) {
            $image = '/storage/placeholder-og.png';
        }
        $image = self::ensureAbsolute((string) $image, $tenant, $urls);
        if (! str_starts_with($image, 'http')) {
            $image = $tenant instanceof Tenant ? $urls->canonicalPath($tenant, $image) : url($image);
        }

        $canonical = $tenant instanceof Tenant
            ? $urls->canonicalRoute($tenant, 'storefront.category', [$category->slug])
            : url('/category/'.$category->slug);
        $locale = $tenant instanceof Tenant ? $tenant->preferredLocale() : (string) app()->getLocale();

        return new self(
            title: $title,
            description: $description,
            canonical: $canonical,
            image: $image,
            imageAlt: $category->name ?? $siteName,
            type: 'website',
            siteName: $siteName,
            robots: $isFiltered ? 'noindex,follow' : null,
            locale: $locale,
        );
    }

    public static function fromBlogPost(BlogPost $post, ?TenantUrlGenerator $urls = null): self
    {
        $urls ??= app(TenantUrlGenerator::class);
        $tenant = $post->tenant ?? tenant();
        if (! $tenant instanceof Tenant && $post->getAttribute('tenant_id')) {
            $tenant = Tenant::withoutGlobalScope('tenant')->find($post->getAttribute('tenant_id'));
        }
        $siteName = $tenant instanceof Tenant ? $tenant->name : (string) config('app.name', 'Store');
        $baseTitle = $post->meta_title ?: $post->title;
        $title = $baseTitle ? $baseTitle.' - '.$siteName : $siteName;

        $description = $post->meta_description ?: $post->excerpt;
        if (! filled($description) && filled($post->content)) {
            $description = Str::limit(strip_tags($post->content), 155, '');
        }
        if (! filled($description) && $tenant instanceof Tenant) {
            $settingsDesc = $tenant->settings?->meta_description_default;
            $description = filled($settingsDesc) ? Str::limit(trim((string) $settingsDesc), 155, '') : null;
        }
        $description = filled($description) ? Str::limit(trim((string) $description), 155, '') : null;

        $image = $post->getFirstMediaUrl('cover', 'large');
        if (! filled($image)) {
            $image = $post->getFirstMediaUrl('cover');
        }
        if (! filled($image) && $tenant instanceof Tenant) {
            $logoPath = $tenant->themeSettings?->logo_path;
            $image = filled($logoPath) ? '/storage/'.ltrim((string) $logoPath, '/') : '/storage/placeholder-og.png';
        }
        if (! filled($image)) {
            $image = '/storage/placeholder-og.png';
        }
        $image = self::ensureAbsolute((string) $image, $tenant, $urls);
        if (! str_starts_with($image, 'http')) {
            $image = $tenant instanceof Tenant ? $urls->canonicalPath($tenant, $image) : url($image);
        }

        $imageAlt = $post->title ?? $siteName;
        $media = $post->getFirstMedia('cover');
        if ($media) {
            $imageAlt = media_alt($media, $imageAlt);
        }

        $canonical = $tenant instanceof Tenant
            ? $urls->canonicalRoute($tenant, 'storefront.blog.show', [$post->slug])
            : url('/blog/'.$post->slug);
        $locale = $tenant instanceof Tenant ? $tenant->preferredLocale() : (string) app()->getLocale();

        return new self(
            title: $title,
            description: $description,
            canonical: $canonical,
            image: $image,
            imageAlt: $imageAlt,
            type: 'article',
            siteName: $siteName,
            robots: null,
            locale: $locale,
        );
    }

    public static function fromBlogIndex(?Tenant $tenant = null, ?TenantUrlGenerator $urls = null): self
    {
        $urls ??= app(TenantUrlGenerator::class);
        $tenant ??= tenant();
        $siteName = $tenant instanceof Tenant ? $tenant->name : (string) config('app.name', 'Store');
        $title = 'Blog - '.$siteName;
        $description = $tenant instanceof Tenant ? ($tenant->settings?->meta_description_default ?? $tenant->themeSettings?->footer_text) : null;
        $description = filled($description) ? Str::limit(trim((string) $description), 155, '') : 'Latest articles, guides and news from '.$siteName;
        $logoPath = $tenant instanceof Tenant ? $tenant->themeSettings?->logo_path : null;
        $image = filled($logoPath) ? '/storage/'.ltrim((string) $logoPath, '/') : '/storage/placeholder-og.png';
        $image = self::ensureAbsolute((string) $image, $tenant, $urls);
        if (! str_starts_with($image, 'http')) {
            $image = $tenant instanceof Tenant ? $urls->canonicalPath($tenant, $image) : url($image);
        }
        $canonical = $tenant instanceof Tenant ? $urls->canonicalPath($tenant, '/blog') : url('/blog');
        $locale = $tenant instanceof Tenant ? $tenant->preferredLocale() : (string) app()->getLocale();

        return new self(
            title: $title,
            description: $description,
            canonical: $canonical,
            image: $image,
            imageAlt: $siteName,
            type: 'website',
            siteName: $siteName,
            robots: null,
            locale: $locale,
        );
    }

    public static function default(?TenantUrlGenerator $urls = null, ?string $path = null): self
    {
        $urls ??= app(TenantUrlGenerator::class);
        $tenant = tenant();
        $siteName = $tenant instanceof Tenant ? $tenant->name : (string) config('app.name', 'Store');
        $title = $siteName;

        if ($tenant instanceof Tenant && filled($tenant->settings?->meta_title_template)) {
            $title = (string) $tenant->settings->meta_title_template;
            $title = str_replace(['{store_name}', '{site_name}'], $siteName, $title);
        }

        $description = null;
        if ($tenant instanceof Tenant) {
            $settingsDesc = $tenant->settings?->meta_description_default;
            $themeDesc = $tenant->themeSettings?->footer_text;
            $raw = filled($settingsDesc) ? $settingsDesc : $themeDesc;
            $description = filled($raw) ? Str::limit(trim((string) $raw), 155, '') : null;
        }

        $logoPath = $tenant instanceof Tenant ? $tenant->themeSettings?->logo_path : null;
        $image = filled($logoPath) ? '/storage/'.ltrim((string) $logoPath, '/') : null;
        if (! filled($image) && $tenant instanceof Tenant) {
            $faviconPath = $tenant->themeSettings?->favicon_path;
            $image = filled($faviconPath) ? '/storage/'.ltrim((string) $faviconPath, '/') : '/storage/placeholder-og.png';
        }
        if (! filled($image)) {
            $image = '/storage/placeholder-og.png';
        }
        $image = self::ensureAbsolute((string) $image, $tenant, $urls);
        if (! str_starts_with($image, 'http')) {
            $image = $tenant instanceof Tenant ? $urls->canonicalPath($tenant, $image) : url($image);
        }

        $canonical = $tenant instanceof Tenant
            ? $urls->canonicalPath($tenant, $path ?? request()->getPathInfo() ?: '/')
            : url($path ?? '/');

        $locale = $tenant instanceof Tenant ? $tenant->preferredLocale() : (string) app()->getLocale();

        return new self(
            title: $title,
            description: $description,
            canonical: $canonical,
            image: $image,
            imageAlt: $siteName,
            type: 'website',
            siteName: $siteName,
            robots: null,
            locale: $locale,
        );
    }

    private static function ensureAbsolute(string $url, ?Tenant $tenant, TenantUrlGenerator $urls): string
    {
        $url = trim($url);
        if ($url === '') {
            return $url;
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }
        if ($tenant instanceof Tenant) {
            $path = str_starts_with($url, '/') ? $url : '/'.$url;

            return $urls->canonicalPath($tenant, $path);
        }

        return url($url);
    }
}
