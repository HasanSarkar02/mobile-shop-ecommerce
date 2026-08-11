@extends('storefront.layout')

@section('title', ($term !== '' ? 'Search results for "' . $term . '"' : 'Search') . ' - ' . tenant()->name)

@section('content')
    @include('storefront.partials.seo-meta', ['robots' => 'noindex,follow'])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-2xl font-bold tracking-tight mb-6">
            @if ($term !== '')
                Search results for &quot;{{ $term }}&quot;
            @else
                Search
            @endif
        </h1>

        <livewire:product-catalog mode="search" :term="$term" />
    </div>
@endsection
