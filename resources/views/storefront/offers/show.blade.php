{{-- resources/views/storefront/offers/show.blade.php --}}
@extends('storefront.layout')
@section('title', $offer->name . ' - ' . tenant()->name)
@section('content')
    @include('storefront.partials.seo-meta', [
        'title' => $offer->name.' - '.tenant()->name,
        'description' => $offer->description ? \Illuminate\Support\Str::limit(strip_tags($offer->description), 150) : 'Special offer from '.tenant()->name,
        'robots' => 'index,follow',
    ])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8 sm:space-y-10">
        <a href="{{ route('storefront.offers.index') }}"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 transition hover:text-[var(--brand)] dark:text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                class="h-4 w-4" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
            </svg>
            All Offers
        </a>

        @php
            $subtitle = $offer->short_tagline ?? ($offer->description ? \Illuminate\Support\Str::limit(strip_tags($offer->description), 140) : null);
        @endphp

        @include('storefront.partials.offers.hero', [
            'badge' => 'Limited Time Only',
            'title' => $offer->name,
            'subtitle' => $subtitle,
            'ctaLabel' => $cards->isNotEmpty() ? 'Shop the Deals' : null,
            'ctaUrl' => $cards->isNotEmpty() ? '#campaign-products' : null,
            'imageUrl' => $offer->heroImageUrl(),
            'imageAlt' => $offer->name,
        ])

        @if ($offer->ends_at)
            @include('storefront.partials.offers.countdown', ['endsAt' => $offer->ends_at])
        @endif

        @if ($coupons->isNotEmpty())
            <section aria-label="Coupons"
                class="rounded-2xl border border-dashed border-[var(--brand)]/40 bg-[var(--brand)]/5 p-5 dark:border-[var(--brand)]/50 dark:bg-[var(--brand)]/10">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($coupons as $coupon)
                        <button type="button"
                            onclick="navigator.clipboard.writeText(@js($coupon->code)) && window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Code {{ $coupon->code }} copied' } }))"
                            class="group flex items-center justify-between rounded-xl border border-[var(--brand)]/30 bg-white px-4 py-3 text-left transition hover:border-[var(--brand)] focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] dark:bg-gray-900">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-bold tracking-wider text-[var(--brand)]">{{ $coupon->code }}</span>
                                <span class="mt-0.5 block truncate text-xs text-gray-500 dark:text-gray-400">{{ $coupon->name }}</span>
                            </span>
                            <span class="ml-3 shrink-0 text-xs font-semibold uppercase tracking-wide text-gray-500 group-hover:text-[var(--brand)] dark:text-gray-400">Copy</span>
                        </button>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($offer->banners->count() > 1)
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @foreach ($offer->banners->skip(1) as $banner)
                    @php($imageUrl = $banner->getFirstMediaUrl('image', 'large'))
                    @if ($imageUrl)
                        <img src="{{ $imageUrl }}" alt="{{ $banner->title }}" loading="lazy"
                            class="w-full rounded-2xl border border-gray-100 object-cover dark:border-gray-800">
                    @endif
                @endforeach
            </div>
        @endif

        @if ($cards->isNotEmpty())
            <section id="campaign-products" class="scroll-mt-24 space-y-5">
                <div>
                    <h2 class="text-xl font-bold tracking-tight">Deals in this campaign</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $cards->count() }} {{ \Illuminate\Support\Pluralizer::plural('product', $cards->count()) }} included
                        @if ($maxDiscount)
                            — up to <span class="font-semibold text-[var(--brand)]">{{ $maxDiscount }}% off</span>
                        @endif
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4 xl:grid-cols-5">
                    @foreach ($cards as $card)
                        @include('storefront.partials.product-card', ['card' => $card])
                    @endforeach
                </div>
            </section>

            @include('storefront.partials.offers.trust-badges')
        @elseif ($offer->description)
            <div class="prose prose-sm max-w-none dark:prose-invert">{!! $offer->description !!}</div>
        @else
            <x-ui.empty-state
                title="No products in this offer yet"
                description="Check back soon — deals are being added."
                actionLabel="Browse all products"
                actionUrl="{{ route('storefront.search') }}" />
        @endif
    </div>
@endsection
