@props(['announcement'])
@if ($announcement)
    <div x-data="{ show: true }" x-show="show"
        class="bg-[var(--brand)] text-white text-sm text-center py-2.5 px-4 relative">
        <a href="{{ $announcement->resolveUrl() ?? '#' }}" class="hover:underline underline-offset-2">
            {{ $announcement->message }}
        </a>
        @if ($announcement->is_dismissible)
            <button @click="show = false" type="button"
                class="absolute right-3 sm:right-4 top-1/2 -translate-y-1/2 p-1 rounded-full hover:bg-white/15 transition"
                aria-label="Dismiss announcement">
                <x-ui.icon name="close" class="w-4 h-4" />
            </button>
        @endif
    </div>
@endif
