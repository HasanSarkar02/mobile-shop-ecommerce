<a href="{{ route('storefront.compare') }}"
    class="relative p-2.5 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition"
    aria-label="Compare products" title="Compare">
    <x-ui.icon name="grid" class="w-[22px] h-[22px]" />
    @if ($count > 0)
        <span
            class="absolute -top-0.5 -right-0.5 bg-[var(--brand)] text-white text-[10px] font-semibold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1">{{ $count }}</span>
    @endif
</a>