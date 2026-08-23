@props([
    'campaign',
    'discount' => null,
    'imageUrl' => null,
    'index' => 0,
])

@php
    $accent = $campaign->accent_color;

    $presets = [
        ['area' => 'bg-emerald-50 dark:bg-emerald-950/40', 'figure' => 'text-emerald-600 dark:text-emerald-400'],
        ['area' => 'bg-violet-50 dark:bg-violet-950/40', 'figure' => 'text-violet-600 dark:text-violet-400'],
        ['area' => 'bg-amber-50 dark:bg-amber-950/40', 'figure' => 'text-amber-600 dark:text-amber-400'],
    ];
    $preset = $presets[((int) $index) % count($presets)];

    if ($campaign->starts_at !== null && $campaign->ends_at !== null) {
        $range = $campaign->starts_at->format('M j').' – '.$campaign->ends_at->format('M j, Y');
    } elseif ($campaign->ends_at !== null) {
        $range = 'Ends '.$campaign->ends_at->format('M j, Y');
    } elseif ($campaign->starts_at !== null) {
        $range = 'Since '.$campaign->starts_at->format('M j, Y');
    } else {
        $range = 'Ongoing';
    }
@endphp

<a href="{{ route('storefront.offers.show', $campaign->slug) }}"
    class="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-[var(--brand)]/40 hover:shadow-lg hover:shadow-gray-900/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] dark:border-gray-800 dark:bg-gray-900">

    {{-- Artwork band: fixed 16/9 ratio reserves space (no layout shift) and
         crops uploads with object-cover; banner artwork keeps object-contain. --}}
    <div class="relative aspect-[16/9] w-full overflow-hidden {{ $accent ? '' : $preset['area'] }}"
        @if ($accent) style="background-color: color-mix(in srgb, {{ $accent }} 8%, transparent)" @endif>

        @if ($imageUrl)
            {{-- Uploaded promo art crops to fill; banner artwork keeps its shape. --}}
            <img src="{{ $imageUrl }}" alt="{{ $campaign->name }}" loading="lazy" decoding="async"
                class="absolute inset-0 h-full w-full {{ ($campaign->card_image !== null || $campaign->hero_image !== null) ? 'object-cover' : 'object-contain p-3' }} transition duration-300 group-hover:scale-[1.03]">
        @else
            <div class="absolute inset-0 flex items-center justify-center" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="1.2"
                    stroke="currentColor"
                    class="h-16 w-16 opacity-70 sm:h-20 sm:w-20 {{ $accent ? '' : $preset['figure'] }}"
                    @if ($accent) style="color: {{ $accent }}" @endif>
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />
                </svg>
            </div>
        @endif

        @if ($discount)
            <span
                class="absolute left-2.5 top-2.5 rounded-lg bg-white/95 px-2 py-1 text-[11px] font-extrabold uppercase tracking-wide text-gray-900 shadow-sm ring-1 ring-black/5 dark:bg-gray-900/95 dark:text-gray-50 dark:ring-white/10 sm:left-3 sm:top-3 sm:px-2.5 sm:text-xs">
                Up to <span class="{{ $accent ? '' : $preset['figure'] }}"
                    @if ($accent) style="color: {{ $accent }}" @endif>{{ $discount }}%</span> OFF
            </span>
        @endif
    </div>

    {{-- Content --}}
    <div class="flex flex-1 flex-col gap-1.5 p-4 sm:p-5">
        <h3 class="text-base font-bold leading-snug tracking-tight text-gray-900 sm:text-lg dark:text-gray-50">
            {{ $campaign->name }}
        </h3>

        @if ($campaign->short_tagline)
            <p class="line-clamp-2 text-xs leading-relaxed text-gray-500 sm:text-sm dark:text-gray-400">
                {{ $campaign->short_tagline }}
            </p>
        @endif

        <span
            class="mt-2 inline-flex w-fit items-center gap-1 rounded-full bg-gray-100 px-2.5 py-1 text-[11px] font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                stroke="currentColor" class="h-3 w-3" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
            </svg>
            {{ $range }}
        </span>
    </div>

    <div
        class="flex items-center justify-between border-t border-gray-100 px-4 py-3 text-sm font-semibold text-[var(--brand)] dark:border-gray-800 sm:px-5">
        Shop Now
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
            stroke="currentColor" class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-0.5"
            aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
        </svg>
    </div>
</a>
