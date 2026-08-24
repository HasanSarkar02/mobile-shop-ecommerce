@props(['percentage'])

{{-- Shared discount-badge primitive (F.4): the red "-X%" pill.
     Extracted verbatim from storefront/partials/product-card.blade.php. --}}
@if ($percentage)
    <span
        class="rounded-md bg-red-600 px-1.5 py-0.5 text-[10px] font-bold leading-none tracking-wide text-white shadow-sm">
        -{{ $percentage }}%
    </span>
@endif
