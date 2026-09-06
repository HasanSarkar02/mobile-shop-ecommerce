@php
    $posts = app(\App\Services\Storefront\HomepageSectionRenderer::class)->resolveBlogPosts($section);
@endphp
@if ($posts->isNotEmpty())
    <section>
        <div class="flex items-baseline justify-between gap-4 mb-4">
            <h2 class="text-xl font-bold tracking-tight">{{ $section->title ?: __('From the Journal') }}</h2>
            @if ($section->resolveUrl())
                <a href="{{ $section->resolveUrl() }}" class="text-sm font-semibold text-[var(--brand)] hover:underline">{{ __('View all') }} &rarr;</a>
            @else
                <a href="{{ route('storefront.blog') }}" class="text-sm font-semibold text-[var(--brand)] hover:underline">{{ __('View all') }} &rarr;</a>
            @endif
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($posts as $post)
                <a href="{{ route('storefront.blog.show', $post->slug) }}" class="group flex flex-col overflow-hidden rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 hover:shadow-soft transition">
                    @if ($url = $post->getFirstMediaUrl('cover', 'large'))
                        <div class="aspect-video overflow-hidden bg-gray-50 dark:bg-gray-800">
                            <img src="{{ $url }}" alt="{{ $post->title }}" loading="lazy" class="h-full w-full object-cover group-hover:scale-[1.02] transition duration-300">
                        </div>
                    @else
                        <div class="aspect-video bg-gray-50 dark:bg-gray-800 flex items-center justify-center">
                            <span class="text-xs text-gray-400">{{ __('No image') }}</span>
                        </div>
                    @endif
                    <div class="p-4 flex flex-col flex-1">
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $post->published_at?->format('M j, Y') }}</p>
                        <h3 class="mt-1 font-semibold leading-tight line-clamp-2 group-hover:text-[var(--brand)] transition">{{ $post->title }}</h3>
                        @if ($post->excerpt)
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300 line-clamp-2">{{ $post->excerpt }}</p>
                        @endif
                        <span class="mt-3 text-sm font-semibold text-[var(--brand)]">{{ __('Read more') }} &rarr;</span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
@endif
