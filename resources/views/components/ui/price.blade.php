@props(['price', 'compareAtPrice' => null, 'size' => 'md'])
@php
    $sizes = ['sm' => 'text-base', 'md' => 'text-2xl', 'lg' => 'text-3xl'];
    $hasDiscount = $compareAtPrice && $compareAtPrice > $price;
@endphp
<div class="flex items-baseline gap-2 flex-wrap">
    <span class="font-bold {{ $sizes[$size] ?? $sizes['md'] }}">{{ money_without_trailing_zeros((int) $price) }}</span>
    @if ($hasDiscount)
        <span class="text-gray-400 line-through text-sm">{{ money_without_trailing_zeros((int) $compareAtPrice) }}</span>
    @endif
</div>
