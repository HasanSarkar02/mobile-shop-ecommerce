@extends('storefront.layout')

@section('title', ($collection->meta_title ?: $collection->name) . ' - ' . tenant()->name)

@section('content')
    @include('storefront.partials.seo-meta', [
        'description' => $collection->meta_description,
        'robots' => $filters->isFiltered() ? 'noindex,follow' : null,
        'canonical' => route('storefront.collection', $collection->slug),
    ])

    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-2">{{ $collection->name }}</h1>
        @if ($collection->description)
            <p class="text-gray-500 mb-4 max-w-2xl">{{ $collection->description }}</p>
        @endif

        <div class="flex justify-end mb-4">
            @include('storefront.partials.sort-select')
        </div>

        @include('storefront.partials.filter-chips')

        <div class="flex flex-col lg:flex-row gap-8">
            @include('storefront.partials.filter-sidebar')

            <div class="flex-1">
                @if ($result['products']->isEmpty())
                    <x-ui.empty-state title="No products found" description="Try adjusting or clearing your filters."
                        actionLabel="Clear filters" :actionUrl="route('storefront.collection', $collection->slug)" />
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
