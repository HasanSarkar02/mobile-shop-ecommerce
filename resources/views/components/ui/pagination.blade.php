@if ($paginator->hasPages())
    <nav class="flex items-center justify-center gap-1 mt-8" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="px-3 py-2 text-gray-300 dark:text-gray-700">‹</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}"
                class="px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition">‹</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-3 py-2 text-gray-400">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span
                            class="px-3 py-2 rounded-lg bg-[var(--brand)] text-white font-medium">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}"
                            class="px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}"
                class="px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition">›</a>
        @else
            <span class="px-3 py-2 text-gray-300 dark:text-gray-700">›</span>
        @endif
    </nav>
@endif
