@props(['price', 'compareAtPrice' => null, 'size' => 'md'])
@php
    $sizes = ['sm' => 'text-base', 'md' => 'text-2xl', 'lg' => 'text-3xl'];
    $hasDiscount = $compareAtPrice && $compareAtPrice > $price;
    $discount = $hasDiscount ? (int) round((($compareAtPrice - $price) / $compareAtPrice) * 100) : null;
@endphp
<div class="flex items-baseline gap-2 flex-wrap">
    <span class="font-bold {{ $sizes[$size] ?? $sizes['md'] }}">৳{{ number_format($price / 100) }}</span>
    @if ($hasDiscount)
        <span class="text-gray-400 line-through text-sm">৳{{ number_format($compareAtPrice / 100) }}</span>
        <x-ui.badge variant="danger">{{ $discount }}% OFF</x-ui.badge>
    @endif
</div>
