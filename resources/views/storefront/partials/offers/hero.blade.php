@props([
    'badge' => 'Limited Time Only',
    'title',
    'subtitle' => null,
    'ctaLabel' => 'Explore Offers',
    'ctaUrl' => null,
    'imageUrl' => null,
    'imageAlt' => null,
])

<section
    class="relative overflow-hidden rounded-3xl border border-[var(--brand)]/15 bg-gradient-to-br from-[var(--brand)]/10 via-[var(--brand)]/5 to-transparent dark:border-[var(--brand)]/25 dark:from-[var(--brand)]/15 dark:via-[var(--brand)]/10 dark:to-transparent">
    <div class="grid items-center gap-6 p-5 sm:gap-8 sm:p-8 lg:grid-cols-2 lg:p-14">
        <div class="order-1">
            <span
                class="inline-flex items-center gap-1.5 rounded-full bg-[var(--brand)]/10 px-3 py-1 text-xs font-semibold text-[var(--brand)] dark:bg-[var(--brand)]/15">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-3.5 w-3.5"
                    aria-hidden="true">
                    <path fill-rule="evenodd"
                        d="M12.963 2.286a.75.75 0 00-1.071-.136 9.742 9.742 0 00-3.539 6.177A7.547 7.547 0 016.648 6.61a.75.75 0 00-1.152-.082A9 9 0 1015.68 4.534a7.46 7.46 0 01-2.717-2.248zM15.75 14.25a3.75 3.75 0 11-7.313-1.172c.628.465 1.35.81 2.133 1a5.99 5.99 0 011.925-3.545 3.75 3.75 0 013.255 3.717z"
                        clip-rule="evenodd" />
                </svg>
                {{ $badge }}
            </span>

            <h1 class="mt-3 text-2xl font-extrabold tracking-tight text-gray-900 sm:text-3xl lg:text-4xl xl:text-[2.75rem] xl:leading-tight dark:text-gray-50">
                {{ $title }}
            </h1>

            @if ($subtitle)
                <p class="mt-2 max-w-md text-sm leading-relaxed text-gray-600 sm:mt-3 sm:text-base dark:text-gray-300">
                    {{ $subtitle }}
                </p>
            @endif

            @if ($ctaLabel && $ctaUrl)
                <a href="{{ $ctaUrl }}"
                    class="mt-5 inline-flex items-center gap-2 rounded-full bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 sm:mt-6 sm:px-6 sm:py-3 dark:focus-visible:ring-offset-gray-950">
                    {{ $ctaLabel }}
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
                        stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                    </svg>
                </a>
            @endif
        </div>

        {{-- Artwork — stacked under the content on mobile, right column on desktop.
             Fixed-height wrapper reserves space so the image never causes layout shift. --}}
        <div class="order-2 flex justify-center lg:justify-end">
            @if ($imageUrl)
                <div class="relative flex h-40 w-full max-w-sm items-center justify-center sm:h-52 lg:h-72 lg:w-auto lg:max-w-none">
                    <span aria-hidden="true"
                        class="absolute left-1/2 top-1/2 h-32 w-32 -translate-x-1/2 -translate-y-1/2 rounded-full bg-[var(--brand)]/15 sm:h-44 sm:w-44 lg:h-60 lg:w-60"></span>
                    <img src="{{ $imageUrl }}" alt="{{ $imageAlt ?? $title }}" loading="eager" decoding="async"
                        class="relative max-h-full max-w-full object-contain drop-shadow-lg">
                </div>
            @else
                <div class="flex h-36 w-36 items-center justify-center rounded-full bg-[var(--brand)]/15 sm:h-48 sm:w-48 xl:h-60 xl:w-60"
                    aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="1.2"
                        stroke="currentColor" class="h-20 w-20 text-[var(--brand)] sm:h-24 sm:w-24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />
                    </svg>
                </div>
            @endif
        </div>
    </div>
</section>
