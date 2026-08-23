{{-- resources/views/storefront/offers/index.blade.php --}}
@extends('storefront.layout')
@section('title', 'Offers - ' . tenant()->name)
@section('content')
    @include('storefront.partials.seo-meta', [
        'title' => 'Offers & Campaigns - '.tenant()->name,
        'description' => $campaigns->isNotEmpty()
            ? 'Exclusive deals and special promotions: '.$campaigns->pluck('name')->take(3)->implode(', ').'.'
            : 'Current promotions, discounts, and special campaigns.',
        'robots' => 'index,follow',
    ])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-10 sm:space-y-12">
        @include('storefront.partials.offers.hero', [
            'badge' => 'Limited Time Only',
            'title' => 'Amazing Offers Just For You!',
            'subtitle' => 'Discover exclusive deals and special promotions on your favorite products.',
            'ctaLabel' => $campaigns->isEmpty() ? null : 'Explore Offers',
            'ctaUrl' => '#all-offers',
            'imageUrl' => $heroImage,
            'imageAlt' => tenant()->name.' offers',
        ])

        @if ($campaigns->isEmpty())
            <div class="rounded-2xl border border-gray-100 dark:border-gray-800">
                <x-ui.empty-state
                    title="No active offers right now"
                    description="Check back soon — new deals land regularly."
                    actionLabel="Browse products"
                    actionUrl="{{ route('storefront.home') }}" />
            </div>
        @else
            <section id="all-offers" class="scroll-mt-24">
                <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight">All Offers</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Explore our current promotions and special campaigns.
                        </p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                        <span class="sr-only">Sort offers</span>
                        <x-ui.select name="sort"
                            onchange="const url = new URL(window.location); url.searchParams.set('sort', this.value); window.location = url;"
                            class="w-auto">
                            <option value="newest" {{ $sort === 'newest' ? 'selected' : '' }}>Newest First</option>
                            <option value="ending_soon" {{ $sort === 'ending_soon' ? 'selected' : '' }}>Ending Soon</option>
                        </x-ui.select>
                    </label>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3">
                    @foreach ($campaigns as $campaign)
                        @include('storefront.partials.offers.offer-card', [
                            'campaign' => $campaign,
                            'discount' => $discounts[$campaign->id] ?? null,
                            'imageUrl' => $campaign->cardImageUrl(),
                            'index' => $loop->index,
                        ])
                    @endforeach
                </div>
            </section>

            @include('storefront.partials.offers.trust-badges')
        @endif
    </div>
@endsection
