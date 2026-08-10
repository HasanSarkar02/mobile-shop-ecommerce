@extends('storefront.layout')

@section('title', 'Search results for "' . $term . '" - ' . tenant()->name)

@section('content')
    @include('storefront.partials.seo-meta', ['robots' => 'noindex,follow'])

    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-4">
            <h1 class="text-2xl font-bold">Search results for "{{ $term }}"</h1>
            @include('storefront.partials.sort-select')
        </div>

        @include('storefront.partials.filter-chips')

        <div class="flex flex-col lg:flex-row gap-8">
            @include('storefront.partials.filter-sidebar')

            <div class="flex-1">
                @if ($result['products']->isEmpty())
                    <x-ui.empty-state title="No results for &quot;{{ $term }}&quot;"
                        description="Try a different search term, or browse our categories instead."
                        actionLabel="Back to Home" :actionUrl="route('storefront.home')" />
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
