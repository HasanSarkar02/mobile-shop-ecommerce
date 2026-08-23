{{-- resources/views/storefront/outlets/index.blade.php --}}
@extends('storefront.layout')
@section('title', 'Our Outlets - ' . tenant()->name)
@section('content')
    @include('storefront.partials.seo-meta', [
        'title' => 'Our Outlets - '.tenant()->name,
        'description' => 'Visit our physical stores and showrooms.',
        'robots' => 'index,follow',
    ])

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-2xl font-bold tracking-tight mb-2">Our Outlets</h1>
        <p class="text-gray-500 mb-6">Visit us in person — experience products before you buy.</p>

        @if ($outlets->isEmpty())
            <div class="rounded-2xl border border-gray-100 dark:border-gray-800 p-8 text-center text-sm text-gray-500">
                No outlet locations are listed right now.
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($outlets as $outlet)
                    <div class="rounded-2xl border border-gray-100 dark:border-gray-800 p-5 flex flex-col gap-3">
                        <div class="flex items-start justify-between gap-3">
                            <h2 class="font-semibold text-lg">{{ $outlet->name }}</h2>
                            @if ($mapUrl = $outlet->googleMapsUrl())
                                <a href="{{ $mapUrl }}" target="_blank" rel="noopener"
                                    class="flex-shrink-0 inline-flex items-center gap-1 text-xs font-medium text-[var(--brand)] hover:underline">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                    </svg>
                                    Map
                                </a>
                            @endif
                        </div>

                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $outlet->fullAddress() }}</p>

                        @if ($outlet->phone)
                            <p class="text-sm">
                                <span class="text-gray-400">Phone:</span>
                                <a href="tel:{{ $outlet->phone }}" class="font-medium hover:text-[var(--brand)]">{{ $outlet->phone }}</a>
                            </p>
                        @endif

                        @if ($outlet->opening_hours)
                            <div class="mt-auto pt-2 border-t border-gray-100 dark:border-gray-800">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Opening Hours</p>
                                <ul class="text-sm text-gray-600 dark:text-gray-300 space-y-0.5">
                                    @foreach ($outlet->opening_hours as $day => $hours)
                                        <li class="flex justify-between gap-4">
                                            <span>{{ ucfirst($day) }}</span>
                                            <span class="tabular-nums">{{ $hours }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection