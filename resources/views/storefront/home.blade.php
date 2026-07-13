@extends('storefront.layout')

@section('title', tenant()->name)

@section('content')
    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="flex flex-wrap gap-3 mb-10">
            @foreach ($categories as $category)
                <a href="{{ route('storefront.category', $category->slug) }}"
                    class="px-4 py-2 rounded-full border border-gray-300 dark:border-gray-700 text-sm">
                    {{ $category->name }} <span class="text-gray-400">({{ $category->products_count }})</span>
                </a>
            @endforeach
        </div>

        <h2 class="text-xl font-bold mb-4">Featured</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            @foreach ($featuredProducts as $product)
                @include('storefront.partials.product-card', ['product' => $product])
            @endforeach
        </div>
    </div>
@endsection
