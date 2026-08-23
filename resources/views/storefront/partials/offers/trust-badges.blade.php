@php
    $badges = [
        [
            'title' => '100% Genuine Products',
            'subtitle' => 'Sourced directly from brands',
            'path' => 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
        ],
        [
            'title' => 'Best Price Guarantee',
            'subtitle' => "Find a lower price? We'll match it",
            'path' => 'M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z M6 6h.008v.008H6V6z',
        ],
        [
            'title' => 'Easy Returns',
            'subtitle' => 'Hassle-free return policy',
            'path' => 'M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99',
        ],
        [
            'title' => 'Secure Payments',
            'subtitle' => '100% secure & protected',
            'path' => 'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z',
        ],
    ];
@endphp

<section
    class="rounded-2xl border border-gray-100 bg-gray-50/60 px-4 py-5 dark:border-gray-800 dark:bg-gray-900/60">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($badges as $badge)
            <div class="flex items-start gap-3">
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white text-[var(--brand)] shadow-sm ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8"
                        stroke="currentColor" class="h-4.5 w-4.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $badge['path'] }}" />
                    </svg>
                </span>
                <div>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $badge['title'] }}</p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $badge['subtitle'] }}</p>
                </div>
            </div>
        @endforeach
    </div>
</section>
