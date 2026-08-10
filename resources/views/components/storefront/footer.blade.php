@props(['footerMenu', 'footerPages', 'theme'])
@php
    $socialLinks = collect($theme?->social_links ?? [])->filter();
@endphp
<footer class="border-t border-gray-200 dark:border-gray-800 mt-20 bg-gray-50 dark:bg-gray-950">
    <div class="max-w-7xl mx-auto px-6 lg:px-8 py-14">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-10">
            {{-- Brand / contact column --}}
            <div class="col-span-2 md:col-span-1">
                <p class="text-lg font-semibold tracking-tight mb-3">{{ tenant()->name }}</p>
                <div class="space-y-1.5 text-sm text-gray-500 dark:text-gray-400">
                    @if (tenant()->contact_phone)
                        <a href="tel:{{ tenant()->contact_phone }}"
                            class="flex items-center gap-2 hover:text-[var(--brand)] transition">
                            <x-ui.icon name="phone" class="w-4 h-4 flex-shrink-0" />
                            {{ tenant()->contact_phone }}
                        </a>
                    @endif
                    @if (tenant()->contact_email)
                        <a href="mailto:{{ tenant()->contact_email }}"
                            class="block hover:text-[var(--brand)] transition">
                            {{ tenant()->contact_email }}
                        </a>
                    @endif
                </div>

                @if ($socialLinks->isNotEmpty())
                    <div class="flex items-center gap-3 mt-4">
                        @foreach (['facebook', 'instagram', 'whatsapp', 'youtube', 'tiktok'] as $platform)
                            @continue(empty($socialLinks[$platform]))
                            <a href="{{ $socialLinks[$platform] }}" target="_blank" rel="noopener noreferrer"
                                class="p-2 rounded-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-500 hover:text-[var(--brand)] hover:border-[var(--brand)] transition"
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
                    <p class="font-semibold text-sm mb-3 text-gray-900 dark:text-gray-100">{{ $group ?: 'More' }}</p>
                    <ul class="space-y-2">
                        @foreach ($pages as $page)
                            <li>
                                <a href="{{ route('storefront.page', $page->slug) }}"
                                    class="text-sm text-gray-500 dark:text-gray-400 hover:text-[var(--brand)] transition">{{ $page->title }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            {{-- Footer menu --}}
            @if ($footerMenu?->topLevelItems->isNotEmpty())
                <div>
                    <p class="font-semibold text-sm mb-3 text-gray-900 dark:text-gray-100">{{ $footerMenu->name }}</p>
                    <ul class="space-y-2">
                        @foreach ($footerMenu->topLevelItems as $item)
                            <li>
                                <a href="{{ $item->resolveUrl() ?? '#' }}"
                                    class="text-sm text-gray-500 dark:text-gray-400 hover:text-[var(--brand)] transition">{{ $item->label }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div
            class="mt-12 pt-6 border-t border-gray-200 dark:border-gray-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-sm text-gray-500 dark:text-gray-400">
            <p>{!! $theme?->footer_text ?? '&copy; ' . now()->year . ' ' . tenant()->name !!}</p>
        </div>
    </div>
</footer>
