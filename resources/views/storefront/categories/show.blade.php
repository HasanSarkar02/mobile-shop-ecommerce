@extends('storefront.layout')

@section('title', ($category->meta_title ?: $category->name) . ' - ' . tenant()->name)

@section('content')
    @include('storefront.partials.seo-meta', [
        'description' => $category->meta_description,
        'robots' => $filters->isFiltered() ? 'noindex,follow' : null,
        'canonical' => route('storefront.category', $category->slug),
    ])

    <div class="max-w-7xl mx-auto px-4 py-8">
        <nav class="text-sm text-gray-500 mb-4" aria-label="Breadcrumb">
            <a href="{{ route('storefront.home') }}" class="hover:text-[var(--brand)]">Home</a>
            <span class="mx-1">/</span>
            <span class="text-gray-700 dark:text-gray-300">{{ $category->name }}</span>
        </nav>

        <div class="flex justify-between items-center mb-4">
            <h1 class="text-2xl font-bold">{{ $category->name }}</h1>
            @include('storefront.partials.sort-select')
        </div>

        @include('storefront.partials.filter-chips')

        <div class="flex flex-col lg:flex-row gap-8">
            @include('storefront.partials.filter-sidebar')

            <div class="flex-1">
                @if ($result['products']->isEmpty())
                    <x-ui.empty-state title="No products found" description="Try adjusting or clearing your filters."
                        actionLabel="Clear filters" :actionUrl="route('storefront.category', $category->slug)" />
                @else
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-6">
                        @foreach ($result['products'] as $product)
                            @include('storefront.partials.product-card', ['product' => $product])
                        @endforeach
                    </div>
                    {{ $result['products']->links() }}
                @endif
            </div>
        </div>
    </div>
@endsection
