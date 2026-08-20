@extends('storefront.layout')

@section('title', ($collection->meta_title ?: $collection->name) . ' - ' . tenant()->name)

@section('content')
    @include('storefront.partials.seo-meta', [
        'description' => $collection->meta_description,
        'robots' => $isFiltered ? 'noindex,follow' : null,
        'canonical' => app(\App\Support\Tenancy\TenantUrlGenerator::class)->canonicalRoute(tenant(), 'storefront.collection', [$collection->slug]),
    ])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-2xl font-bold tracking-tight mb-2">{{ $collection->name }}</h1>
        @if ($collection->description)
            <p class="text-gray-500 dark:text-gray-400 mb-6 max-w-2xl">{{ $collection->description }}</p>
        @endif

        <livewire:product-catalog mode="collection" :slug="$collection->slug" />
    </div>
@endsection
