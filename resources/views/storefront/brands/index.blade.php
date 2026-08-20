@extends('storefront.layout')

@section('title', 'All Brands - ' . tenant()->name)

@section('content')
    @include('storefront.partials.seo-meta', [
        'canonical' => app(\App\Support\Tenancy\TenantUrlGenerator::class)->canonicalRoute(tenant(), 'storefront.brands.index'),
    ])

    <div class="max-w-7xl mx-auto px-4 py-8">
        <nav class="text-sm text-gray-500 mb-4" aria-label="Breadcrumb">
            <a href="{{ app(\App\Support\Tenancy\TenantUrlGenerator::class)->canonicalRoute(tenant(), 'storefront.home') }}" class="hover:text-[var(--brand)]">Home</a>
            <span class="mx-1">/</span>
            <span class="text-gray-700 dark:text-gray-300">Brands</span>
        </nav>

        <h1 class="text-2xl font-bold mb-6">All Brands</h1>

        @if ($brands->isEmpty())
            <x-ui.empty-state title="No brands yet" description="Brands will appear here as soon as products are added." />
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 sm:gap-5">
                @foreach ($brands as $brand)
                    <a href="{{ app(\App\Support\Tenancy\TenantUrlGenerator::class)->canonicalRoute(tenant(), 'storefront.brand', [$brand->slug]) }}"
                        class="group flex flex-col items-center gap-2.5 text-center">
                        <div
                            class="w-full aspect-square rounded-2xl overflow-hidden bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-800 flex items-center justify-center transition group-hover:border-[var(--brand)] group-hover:shadow-soft">
                            @if ($brand->logo_path)
                                <img src="{{ asset('storage/' . $brand->logo_path) }}" alt="{{ $brand->name }}"
                                    loading="lazy" decoding="async"
                                    class="w-full h-full object-contain p-4">
                            @else
                                <span
                                    class="text-2xl font-semibold text-gray-300 dark:text-gray-700">{{ mb_substr($brand->name, 0, 1) }}</span>
                            @endif
                        </div>
                        <span
                            class="text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 group-hover:text-[var(--brand)] transition line-clamp-1">{{ $brand->name }}</span>
                        <span class="text-xs text-gray-400">{{ $brand->products_count }} products</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endsection
