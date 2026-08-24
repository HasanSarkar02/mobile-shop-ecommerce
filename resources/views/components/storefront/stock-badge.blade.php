@props(['show' => false])

{{-- Shared stock-badge primitive (F.4): the "Out of Stock" overlay pill laid
     over the product image area. Extracted verbatim from
     storefront/partials/product-card.blade.php. --}}
@if ($show)
    <div class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/5">
        <span class="rounded-full bg-gray-900/90 px-3 py-1 text-xs font-semibold text-white">
            Out of Stock
        </span>
    </div>
@endif
