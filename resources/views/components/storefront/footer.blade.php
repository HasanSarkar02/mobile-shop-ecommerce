@props(['footerMenu', 'footerPages', 'theme'])
@php
    $socialLinks = collect($theme?->social_links ?? [])->filter();
@endphp
<footer class="border-t border-black/10 mt-20 bg-[var(--brand)] dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 lg:px-8 py-14">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-x-8 gap-y-10">
            {{-- Brand / contact column --}}
            <div class="sm:col-span-2 lg:col-span-1">
                <p class="text-lg font-semibold tracking-tight text-white">{{ tenant()->name }}</p>
                <div class="mt-3 space-y-1.5 text-sm text-white/85">
                    @if (tenant()->contact_phone)
                        <a href="tel:{{ tenant()->contact_phone }}"
                            class="inline-flex items-center gap-2 hover:text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white rounded">
                            <x-ui.icon name="phone" class="w-4 h-4 flex-shrink-0" />
                            {{ tenant()->contact_phone }}
                        </a>
                    @endif
                    @if (tenant()->contact_email)
                        <a href="mailto:{{ tenant()->contact_email }}"
                            class="block hover:text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white rounded">
                            {{ tenant()->contact_email }}
                        </a>
                    @endif
                </div>

                @if ($socialLinks->isNotEmpty())
                    <div class="flex items-center gap-3 mt-5">
                        @foreach (['facebook', 'instagram', 'whatsapp', 'youtube', 'tiktok'] as $platform)
                            @continue(empty($socialLinks[$platform]))
                            <a href="{{ $socialLinks[$platform] }}" target="_blank" rel="noopener noreferrer"
                                class="p-2 rounded-full bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 hover:text-gray-900 hover:shadow-md transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                                aria-label="{{ ucfirst($platform) }}">
                                <x-ui.icon :name="$platform" :solid="true" class="w-4 h-4" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- CMS static page groups --}}
            @foreach ($footerPages as $group => $pages)
                <div>
                    <p class="font-semibold text-sm mb-3 text-white">{{ $group ?: 'More' }}</p>
                    <ul class="space-y-2">
                        @foreach ($pages as $page)
                            <li>
                                <a href="{{ route('storefront.page', $page->slug) }}"
                                    class="text-sm text-white/85 hover:text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white rounded">{{ $page->title }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            {{-- Customer service / account column --}}
            <div>
                <p class="font-semibold text-sm mb-3 text-white">Customer Service</p>
                <ul class="space-y-2">
                    <li>
                        <a href="{{ route('storefront.track-order.form') }}"
                            class="text-sm text-white/85 hover:text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white rounded">Track Order</a>
                    </li>
                    <li>
                        <a href="{{ route('storefront.faq') }}"
                            class="text-sm text-white/85 hover:text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white rounded">FAQ</a>
                    </li>
                </ul>

                @auth('customer')
                    <p class="font-semibold text-sm mt-6 mb-3 text-white">My Account</p>
                    <ul class="space-y-2">
                        <li>
                            <a href="{{ route('storefront.account.dashboard') }}"
                                class="text-sm text-white/85 hover:text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white rounded">Dashboard</a>
                        </li>
                        <li>
                            <a href="{{ route('storefront.account.orders') }}"
                                class="text-sm text-white/85 hover:text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white rounded">Orders</a>
                        </li>
                        <li>
                            <a href="{{ route('storefront.account.addresses') }}"
                                class="text-sm text-white/85 hover:text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white rounded">Addresses</a>
                        </li>
                        <li>
                            <a href="{{ route('storefront.account.profile') }}"
                                class="text-sm text-white/85 hover:text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white rounded">Profile</a>
                        </li>
                    </ul>
                @else
                    <p class="font-semibold text-sm mt-6 mb-3 text-white">My Account</p>
                    <ul class="space-y-2">
                        <li>
                            <a href="{{ route('storefront.login') }}"
                                class="text-sm text-white/85 hover:text-white transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white rounded">Login / Register</a>
                        </li>
                    </ul>
                @endauth
            </div>
        </div>

        {{-- Newsletter --}}
        <div class="mt-12 border-t border-white/20 dark:border-gray-800 pt-10">
            <div class="rounded-2xl border border-white/20 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 sm:p-8">
                <div class="flex flex-col md:flex-row md:items-center gap-4 md:gap-8">
                    <div class="md:max-w-sm md:flex-1">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-50">Stay in the loop</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Subscribe for new arrivals, price drops, and exclusive offers straight to your inbox.
                        </p>
                    </div>
                    <form action="{{ route('storefront.newsletter.subscribe') }}" method="POST" class="flex-1">
                        @csrf
                        <label for="footer-newsletter-email" class="sr-only">Email address</label>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <input id="footer-newsletter-email" type="email" name="email" required value="{{ old('email') }}"
                                placeholder="you@example.com"
                                class="flex-1 min-w-0 px-4 py-3 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--brand)]">
                            <button type="submit"
                                class="px-6 py-3 rounded-lg bg-[var(--brand)] text-white font-medium hover:opacity-90 transition flex-shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)] focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-900">
                                Subscribe
                            </button>
                        </div>
                        @error('email')
                            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </form>
                </div>
            </div>
        </div>

        {{-- Trust / payment strip (static copy only) --}}
        <div class="mt-8 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-sm text-white/85">
            @foreach (['Secure Payments', 'Cash on Delivery', 'Nationwide Delivery'] as $trust)
                <span class="inline-flex items-center gap-2">
                    <svg class="w-4 h-4 text-white flex-shrink-0" fill="none" viewBox="0 0 24 24"
                        stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ $trust }}
                </span>
            @endforeach
        </div>

        {{-- Bottom bar --}}
        <div
            class="mt-8 pt-6 border-t border-white/20 dark:border-gray-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-white/85">
            <p>{!! $theme?->footer_text ?? '&copy; ' . now()->year . ' ' . tenant()->name !!}</p>
        </div>
    </div>
</footer>