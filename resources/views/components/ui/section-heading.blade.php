@props(['title', 'actionLabel' => null, 'actionUrl' => null])
<div class="flex justify-between items-center mb-4">
    @if ($title)
        <h2 class="text-xl font-bold">{{ $title }}</h2>
    @endif
    @if ($actionLabel && $actionUrl)
        <a href="{{ $actionUrl }}"
            class="text-sm text-[var(--brand)] font-medium hover:underline flex-shrink-0">{{ $actionLabel }} →</a>
    @endif
</div>
