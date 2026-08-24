@props(['rating', 'count'])

{{-- Shared product-rating primitive (F.4): inline star + score + review count.
     The fixed-height wrapper keeps grid rows aligned whether or not a rating
     exists. Extracted verbatim from storefront/partials/product-card.blade.php. --}}
@php
    $count = $count ?? 0;
    $hasRating = $count > 0 && $rating !== null;
@endphp
<div class="mt-1 flex min-h-[1.1rem] items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
    @if ($hasRating)
        <span class="text-amber-500" aria-hidden="true">★</span>
        <span
            class="font-medium text-gray-700 dark:text-gray-300">{{ number_format((float) $rating, 1) }}</span>
        <span>({{ $count }})</span>
    @endif
</div>
