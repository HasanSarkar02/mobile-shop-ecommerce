@extends('storefront.layout')

@section('title', 'Compare Products - ' . tenant()->name)

@section('content')
    @include('storefront.partials.seo-meta', ['robots' => 'noindex,nofollow'])

    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Compare Products</h1>

        @if ($products->isEmpty())
            <p class="text-gray-500">Add products to compare from any product page.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <tr>
                        <td class="w-40"></td>
                        @foreach ($products as $product)
                            <td class="p-4 text-center">
                                <img src="{{ $product->getFirstMediaUrl('images', 'thumb') }}"
                                    class="w-20 h-20 mx-auto object-cover rounded-lg">
                                <p class="font-medium mt-2">{{ $product->name }}</p>
                                <p class="font-bold">৳{{ number_format($product->base_price / 100) }}</p>
                            </td>
                        @endforeach
                    </tr>
                    @php($allAttrs = $products->flatMap->attributeValues->whereNull('product_variant_id')->pluck('attributeDefinition.label')->unique())
                    @foreach ($allAttrs as $label)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="p-3 font-medium text-gray-500">{{ $label }}</td>
                            @foreach ($products as $product)
                                @php($value = $product->attributeValues->whereNull('product_variant_id')->first(fn($v) => $v->attributeDefinition->label === $label))
                                <td class="p-3 text-center">{{ $value?->displayValue() ?? '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </div>
@endsection
