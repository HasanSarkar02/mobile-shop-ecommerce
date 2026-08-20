@extends('storefront.layout')

@section('title', ($brand->meta_title ?: $brand->name) . ' - ' . tenant()->name)

@section('content')
    @include('storefront.partials.seo-meta', [
        'description' => $brand->meta_description,
        'robots' => $isFiltered ? 'noindex,follow' : null,
        'canonical' => app(\App\Support\Tenancy\TenantUrlGenerator::class)->canonicalRoute(tenant(), 'storefront.brand', [$brand->slug]),
    ])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <nav class="text-sm text-gray-500 mb-4" aria-label="Breadcrumb">
            <a href="{{ app(\App\Support\Tenancy\TenantUrlGenerator::class)->canonicalRoute(tenant(), 'storefront.home') }}" class="hover:text-[var(--brand)]">Home</a>
            <span class="mx-1">/</span>
            <span class="text-gray-700 dark:text-gray-300">{{ $brand->name }}</span>
        </nav>

        <h1 class="text-2xl font-bold tracking-tight mb-6">{{ $brand->name }}</h1>

        <livewire:product-catalog mode="brand" :slug="$brand->slug" />
    </div>
@endsection
