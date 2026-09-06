@extends('storefront.layout')

@section('title', $seo->title)

@section('content')
    <x-seo.meta :seo="$seo" />

    <div class="{{ \App\Support\IndustryConfig::currentGet('ui.container_class', 'max-w-7xl mx-auto') }} px-4 sm:px-6 lg:px-8 py-8">
        <nav class="text-sm text-gray-500 mb-4" aria-label="Breadcrumb">
            <a href="{{ app(\App\Support\Tenancy\TenantUrlGenerator::class)->canonicalRoute(tenant(), 'storefront.home') }}" class="hover:text-[var(--brand)]">Home</a>
            <span class="mx-1">/</span>
            <span class="text-gray-700 dark:text-gray-300">{{ $category->name }}</span>
        </nav>

        <h1 class="text-2xl font-bold tracking-tight mb-6">{{ $category->name }}</h1>

        <livewire:product-catalog mode="category" :slug="$category->slug" />

        @if (!empty($relatedBlogPosts) && $relatedBlogPosts->isNotEmpty())
            @include('storefront.partials.blog-rail', ['posts' => $relatedBlogPosts, 'title' => __('Guides & Articles')])
        @endif
    </div>
@endsection
