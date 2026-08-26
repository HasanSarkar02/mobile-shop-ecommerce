{{-- resources/views/storefront/preorders/index.blade.php --}}
@extends('storefront.layout')
@section('title', __('Pre-Orders') . ' - ' . tenant()->name)
@section('content')
    @include('storefront.partials.seo-meta', ['robots' => 'index,follow'])

    <div class="{{ \App\Support\IndustryConfig::currentGet('ui.container_class', 'max-w-7xl mx-auto') }} px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center gap-3 mb-6">
            <span class="px-3 py-1 rounded-full bg-purple-600 text-white text-xs font-bold">{{ __('PRE-ORDER') }}</span>
            <h1 class="text-2xl font-bold">{{ __('Pre-Orders') }}</h1>
        </div>
        <p class="text-sm text-gray-500 mb-6">{{ __('Reserve upcoming products — pay now, ships around the estimated availability date.') }}</p>

        @if ($products->isEmpty())
            <div class="rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-10 sm:p-12 text-center">
                <div class="mx-auto max-w-md">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-purple-50 dark:bg-purple-950 text-purple-600 dark:text-purple-400">
                        <x-ui.icon name="clock" class="w-6 h-6" />
                    </div>
                    <h2 class="mt-4 text-base font-semibold text-gray-900 dark:text-white">{{ __('No pre-orders yet') }}</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ __('We don\'t have any pre-order products available right now. Check back soon or explore our full catalog.') }}</p>
                    <div class="mt-6 flex flex-col sm:flex-row gap-3 justify-center">
                        <x-ui.button as="a" href="{{ route('storefront.home') }}" variant="primary">{{ __('Browse all products') }}</x-ui.button>
                        <x-ui.button as="a" href="{{ route('storefront.categories.index') }}" variant="secondary">{{ __('Explore categories') }}</x-ui.button>
                    </div>
                </div>
            </div>
        @else
            <div class="grid {{ \App\Support\IndustryConfig::currentGet('ui.grid_class', 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4') }} gap-4">
                @foreach ($cards as $card)
                    <x-dynamic-component :component="\App\Support\IndustryConfig::currentGet('ui.card_component', 'storefront.product-cards.default')" :card="$card" />
                @endforeach
            </div>
            <div class="mt-8">{{ $products->links() }}</div>
        @endif
    </div>
@endsection