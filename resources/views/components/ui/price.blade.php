@props(['price', 'compareAtPrice' => null, 'size' => 'md'])
@php
    $sizes = ['sm' => 'text-base', 'md' => 'text-2xl', 'lg' => 'text-3xl'];
    $symbol = currency_symbol();
    $hasDiscount = $compareAtPrice && $compareAtPrice > $price;
@endphp
<div class="flex items-baseline gap-2 flex-wrap">
    <span class="font-bold {{ $sizes[$size] ?? $sizes['md'] }}">{{ $symbol }}{{ number_format($price / 100) }}</span>
    @if ($hasDiscount)
        <span class="text-gray-400 line-through text-sm">{{ $symbol }}{{ number_format($compareAtPrice / 100) }}</span>
    @endif
</div>
