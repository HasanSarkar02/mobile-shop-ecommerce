@php
    $translation = $product->translation('en');
    $firstVariant = $product->variants->first();
@endphp
<a href="{{ route('storefront.product', $translation?->slug) }}" class="block group">
    <div class="aspect-square rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-900">
        @if ($product->getFirstMediaUrl('images', 'thumb'))
            <img src="{{ $product->getFirstMediaUrl('images', 'thumb') }}" alt="{{ $translation?->name }}"
                class="w-full h-full object-cover group-hover:scale-105 transition">
        @endif
    </div>
    <p class="mt-3 text-sm font-medium">{{ $translation?->name }}</p>
    @if ($firstVariant)
        <div class="flex items-center gap-2 mt-1">
            <span class="font-bold">৳{{ number_format($firstVariant->price / 100) }}</span>
            @if ($firstVariant->compare_at_price)
                <span
                    class="text-xs text-gray-400 line-through">৳{{ number_format($firstVariant->compare_at_price / 100) }}</span>
            @endif
        </div>
        @if ($firstVariant->isPreOrder())
            <span class="text-xs text-amber-600">Pre-Order</span>
        @endif
    @endif
</a>
