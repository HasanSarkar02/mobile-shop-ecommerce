@extends('storefront.layout')

@section('title', 'All Brands - ' . tenant()->name)

@section('content')
    @include('storefront.partials.seo-meta', [
        'canonical' => app(\App\Support\Tenancy\TenantUrlGenerator::class)->canonicalRoute(tenant(), 'storefront.brands.index'),
    ])

    <div class="{{ \App\Support\IndustryConfig::currentGet('ui.container_class', 'max-w-7xl mx-auto') }} px-4 sm:px-6 lg:px-8 py-8">
        <nav class="text-sm text-gray-500 mb-4" aria-label="Breadcrumb">
            <a href="{{ app(\App\Support\Tenancy\TenantUrlGenerator::class)->canonicalRoute(tenant(), 'storefront.home') }}" class="hover:text-[var(--brand)]">Home</a>
            <span class="mx-1">/</span>
            <span class="text-gray-700 dark:text-gray-300">Brands</span>
        </nav>

        <h1 class="text-2xl font-bold mb-6">All Brands</h1>

        @if ($brands->isEmpty())
            <x-ui.empty-state title="No brands yet" description="Brands will appear here as soon as products are added." />
        @else
            @php
                $palette = [
                    ['bg' => 'bg-orange-50 dark:bg-orange-950/40', 'text' => 'text-orange-400 dark:text-orange-500'],
                    ['bg' => 'bg-blue-50 dark:bg-blue-950/40', 'text' => 'text-blue-400 dark:text-blue-500'],
                    ['bg' => 'bg-emerald-50 dark:bg-emerald-950/40', 'text' => 'text-emerald-400 dark:text-emerald-500'],
                    ['bg' => 'bg-violet-50 dark:bg-violet-950/40', 'text' => 'text-violet-400 dark:text-violet-500'],
                    ['bg' => 'bg-rose-50 dark:bg-rose-950/40', 'text' => 'text-rose-400 dark:text-rose-500'],
                ];
            @endphp
            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-4 sm:gap-5">
                @foreach ($brands as $brand)
                    @php $swatch = $palette[crc32($brand->name) % count($palette)]; @endphp
                    <a href="{{ app(\App\Support\Tenancy\TenantUrlGenerator::class)->canonicalRoute(tenant(), 'storefront.brand', [$brand->slug]) }}"
                        class="group flex flex-col items-center gap-1.5 text-center">
                        <div
                            class="relative w-20 h-20 sm:w-24 sm:h-24 rounded-full overflow-hidden bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 flex items-center justify-center transition-all duration-300 group-hover:border-[var(--brand)] group-hover:shadow-soft group-hover:-translate-y-0.5">
                            <div class="absolute inset-0 flex items-center justify-center {{ $swatch['bg'] }}">
                                <span class="text-xl font-bold {{ $swatch['text'] }}">{{ mb_substr($brand->name, 0, 1) }}</span>
                            </div>
                            @if ($brand->logo_path)
                                <img src="{{ asset('storage/' . $brand->logo_path) }}" alt="{{ $brand->name }}"
                                    loading="lazy" decoding="async"
                                    class="relative w-full h-full object-contain p-4 bg-white dark:bg-gray-900 opacity-0 transition-all duration-500 ease-out scale-95 group-hover:scale-100"
                                    onload="this.style.opacity='1'; this.previousElementSibling.style.display='none';"
                                    onerror="this.remove();">
                            @endif
                        </div>
                        <span
                            class="text-xs sm:text-sm font-medium text-gray-700 dark:text-gray-300 group-hover:text-[var(--brand)] transition-colors line-clamp-2 leading-tight">{{ $brand->name }}</span>
                        <span class="text-xs text-gray-400">{{ $brand->products_count }} products</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endsection
