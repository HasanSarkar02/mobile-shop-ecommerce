@php($products = app(\App\Services\Storefront\HomepageSectionRenderer::class)->resolveProducts($section))
@if ($products->isNotEmpty())
    <div>
        <div class="flex justify-between items-center mb-4">
            @if ($section->title)
                <h2 class="text-xl font-bold">{{ $section->title }}</h2>
            @endif
            @if ($section->link_type->value !== 'none')
                <a href="{{ $section->resolveUrl() }}" class="text-sm text-[var(--brand)]">View all</a>
            @endif
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            @foreach ($products as $product)
                @include('storefront.partials.product-card', ['product' => $product])
            @endforeach
        </div>
    </div>
@endif
