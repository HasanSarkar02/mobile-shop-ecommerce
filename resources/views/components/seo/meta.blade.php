@props([
    'seo' => null,
    'title' => null,
    'description' => null,
    'canonical' => null,
    'image' => null,
    'imageAlt' => null,
    'type' => null,
    'siteName' => null,
    'robots' => null,
    'locale' => null,
])

@php
    /** @var \App\Support\Seo\SeoData|null $seo */
    $seoTitle = $seo?->title ?? $title;
    $seoDescription = $seo?->description ?? $description;
    $seoCanonical = $seo?->canonical ?? $canonical;
    $seoImage = $seo?->image ?? $image;
    $seoImageAlt = $seo?->imageAlt ?? $imageAlt ?? $seoTitle ?? '';
    $seoType = $seo?->type ?? $type ?? 'website';
    $seoSiteName = $seo?->siteName ?? $siteName ?? tenant()?->name ?? config('app.name');
    $seoRobots = $seo?->robots ?? $robots;
    $seoLocale = $seo?->locale ?? $locale ?? app()->getLocale();

    // Fallback canonical to TenantUrlGenerator if not provided
    if (! filled($seoCanonical)) {
        $seoCanonical = app(\App\Support\Tenancy\TenantUrlGenerator::class)->canonicalPath(tenant(), request()->getPathInfo() ?: '/');
    }

    // Normalize locale for OG (en -> en_US, bn -> bn_BD)
    $ogLocale = match ($seoLocale) {
        'bn' => 'bn_BD',
        'en' => 'en_US',
        default => str_replace('-', '_', $seoLocale),
    };
@endphp

@push('meta')
    @if (filled($seoDescription))
        <meta name="description" content="{{ $seoDescription }}">
    @endif
    <link rel="canonical" href="{{ $seoCanonical }}">
    @if (filled($seoRobots))
        <meta name="robots" content="{{ $seoRobots }}">
    @endif

    {{-- Open Graph --}}
    @if (filled($seoTitle))
        <meta property="og:title" content="{{ $seoTitle }}">
    @endif
    @if (filled($seoDescription))
        <meta property="og:description" content="{{ $seoDescription }}">
    @endif
    @if (filled($seoImage))
        <meta property="og:image" content="{{ $seoImage }}">
        <meta property="og:image:alt" content="{{ $seoImageAlt }}">
    @endif
    <meta property="og:url" content="{{ $seoCanonical }}">
    <meta property="og:type" content="{{ $seoType }}">
    <meta property="og:site_name" content="{{ $seoSiteName }}">
    <meta property="og:locale" content="{{ $ogLocale }}">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="{{ filled($seoImage) ? 'summary_large_image' : 'summary' }}">
    @if (filled($seoTitle))
        <meta name="twitter:title" content="{{ $seoTitle }}">
    @endif
    @if (filled($seoDescription))
        <meta name="twitter:description" content="{{ $seoDescription }}">
    @endif
    @if (filled($seoImage))
        <meta name="twitter:image" content="{{ $seoImage }}">
        <meta name="twitter:image:alt" content="{{ $seoImageAlt }}">
    @endif
@endpush
