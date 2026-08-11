@extends('storefront.layout')

@section('title', ($category->meta_title ?: $category->name) . ' - ' . tenant()->name)

@section('content')
    @include('storefront.partials.seo-meta', [
        'description' => $category->meta_description,
        'robots' => $isFiltered ? 'noindex,follow' : null,
        'canonical' => route('storefront.category', $category->slug),
    ])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <nav class="text-sm text-gray-500 mb-4" aria-label="Breadcrumb">
            <a href="{{ route('storefront.home') }}" class="hover:text-[var(--brand)]">Home</a>
            <span class="mx-1">/</span>
            <span class="text-gray-700 dark:text-gray-300">{{ $category->name }}</span>
        </nav>

        <h1 class="text-2xl font-bold tracking-tight mb-6">{{ $category->name }}</h1>

        <livewire:product-catalog mode="category" :slug="$category->slug" />
    </div>
@endsection
