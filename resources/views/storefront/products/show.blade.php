@extends('storefront.layout')

@section('title', ($product->translation('en')?->name ?? 'Product') . ' - ' . tenant()->name)

@section('content')
    <div class="max-w-7xl mx-auto px-4 py-8" x-data="productVariantPicker(@js($variantsData), @js($productImages), {{ $product->variants->first()?->id ?? 'null' }})" x-init="init()">
        <nav class="text-sm text-gray-500 mb-6">
            <a href="{{ route('storefront.home') }}">Home</a>
            @if ($product->category)
                &gt; <a
                    href="{{ route('storefront.category', $product->category->slug) }}">{{ $product->category->name }}</a>
            @endif
            &gt; {{ $product->translation('en')?->name }}
        </nav>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
            <div>
                <div class="aspect-square rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-900">
                    <template x-for="image in currentImages()" :key="image">
                        <img x-show="image === activeImage" :src="image" class="w-full h-full object-cover">
                    </template>
                </div>
                <div class="flex gap-2 mt-4">
                    <template x-for="image in currentImages()" :key="image">
                        <button @click="activeImage = image" class="w-16 h-16 rounded-lg overflow-hidden border-2"
                            :class="activeImage === image ? 'border-[var(--brand)]' : 'border-transparent'">
                            <img :src="image" class="w-full h-full object-cover">
                        </button>
                    </template>
                </div>
            </div>

            <div>
                @if ($product->brand)
                    <p class="text-sm text-gray-500">{{ $product->brand->name }}</p>
                @endif
                <h1 class="text-2xl font-bold mt-1">{{ $product->translation('en')?->name }}</h1>

                <div class="flex items-baseline gap-3 mt-4">
                    <span class="text-2xl font-bold" x-text="formatPrice(current().price)"></span>
                    <span class="text-gray-400 line-through" x-show="current().compare_at_price"
                        x-text="formatPrice(current().compare_at_price)"></span>
                    <span class="text-sm px-2 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300"
                        x-show="discountPercent()" x-text="discountPercent() + '% off'"></span>
                </div>
                <p class="text-sm mt-1"
                    :class="current().availability === 'pre_order' ? 'text-amber-600' : 'text-green-600'"
                    x-text="availabilityLabel()"></p>

                <template x-if="colors().length">
                    <div class="mt-6">
                        <p class="text-sm font-medium mb-2">Color</p>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="color in colors()" :key="color">
                                <button @click="selected.color = color; updateVariant()"
                                    class="px-3 py-1.5 rounded-full border text-sm"
                                    :class="selected.color === color ? 'border-[var(--brand)] text-[var(--brand)]' :
                                        'border-gray-300 dark:border-gray-700'"
                                    x-text="color"></button>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="storages().length">
                    <div class="mt-4">
                        <p class="text-sm font-medium mb-2">Storage</p>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="storage in storages()" :key="storage">
                                <button @click="selected.storage = storage; updateVariant()"
                                    class="px-3 py-1.5 rounded-full border text-sm"
                                    :class="selected.storage === storage ? 'border-[var(--brand)] text-[var(--brand)]' :
                                        'border-gray-300 dark:border-gray-700'"
                                    x-text="storage + 'GB'"></button>
                            </template>
                        </div>
                    </div>
                </template>

                <template x-if="regions().length">
                    <div class="mt-4">
                        <p class="text-sm font-medium mb-2">Region</p>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="region in regions()" :key="region">
                                <button @click="selected.region = region; updateVariant()"
                                    class="px-3 py-1.5 rounded-full border text-sm"
                                    :class="selected.region === region ? 'border-[var(--brand)] text-[var(--brand)]' :
                                        'border-gray-300 dark:border-gray-700'"
                                    x-text="region"></button>
                            </template>
                        </div>
                    </div>
                </template>

                @if ($product->emiPlans->isNotEmpty())
                    <div class="mt-6 p-4 rounded-xl bg-gray-50 dark:bg-gray-900">
                        <p class="text-sm font-medium mb-2">EMI Available</p>
                        <ul class="text-sm space-y-1">
                            @foreach ($product->emiPlans as $plan)
                                <li>
                                    {{ $plan->bank_name }} — {{ $plan->tenure_months }} months
                                    (~<span
                                        x-text="formatPrice(Math.round(current().price * (1 + {{ $plan->interest_rate }} / 100) / {{ $plan->tenure_months }}))"></span>/mo)
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <button class="mt-6 w-full py-3 rounded-xl bg-[var(--brand)] text-white font-medium"
                    x-text="current().availability === 'pre_order' ? 'Pre-Order Now' : 'Add to Cart'"></button>
            </div>
        </div>

        <div class="mt-16" x-data="{ tab: 'spec' }">
            <div class="flex gap-2 border-b border-gray-200 dark:border-gray-800">
                <button @click="tab = 'spec'" :class="tab === 'spec' ? 'border-b-2 border-[var(--brand)]' : ''"
                    class="px-4 py-2 text-sm font-medium">Specification</button>
                <button @click="tab = 'desc'" :class="tab === 'desc' ? 'border-b-2 border-[var(--brand)]' : ''"
                    class="px-4 py-2 text-sm font-medium">Description</button>
                @if ($product->translation('en')?->warranty_info)
                    <button @click="tab = 'warranty'" :class="tab === 'warranty' ? 'border-b-2 border-[var(--brand)]' : ''"
                        class="px-4 py-2 text-sm font-medium">Warranty</button>
                @endif
            </div>

            <div x-show="tab === 'spec'" class="mt-6">
                <table class="w-full text-sm">
                    @foreach ($specifications as $value)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="py-2 pr-4 text-gray-500 w-1/3">{{ $value->attributeDefinition->label }}</td>
                            <td class="py-2">{{ $value->displayValue() }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>

            <div x-show="tab === 'desc'" class="mt-6 prose dark:prose-invert max-w-none">
                {!! nl2br(e($product->translation('en')?->description)) !!}
            </div>

            @if ($product->translation('en')?->warranty_info)
                <div x-show="tab === 'warranty'" class="mt-6 prose dark:prose-invert max-w-none">
                    {!! nl2br(e($product->translation('en')->warranty_info)) !!}
                </div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        function productVariantPicker(variants, productImages, initialId) {
            return {
                variants,
                productImages,
                selected: {},
                activeImage: null,
                currentVariantId: initialId,
                init() {
                    const first = this.current();
                    this.selected.color = first.color;
                    this.selected.storage = first.storage_gb;
                    this.selected.region = first.region;
                    this.activeImage = this.currentImages()[0] ?? null;
                },
                current() {
                    return this.variants.find(v => v.id === this.currentVariantId) ?? this.variants[0];
                },
                updateVariant() {
                    const match = this.variants.find(v =>
                        (!this.selected.color || v.color === this.selected.color) &&
                        (!this.selected.storage || v.storage_gb === this.selected.storage) &&
                        (!this.selected.region || v.region === this.selected.region)
                    );
                    if (match) {
                        this.currentVariantId = match.id;
                        this.activeImage = this.currentImages()[0] ?? null;
                    }
                },
                colors() {
                    return [...new Set(this.variants.map(v => v.color).filter(Boolean))];
                },
                storages() {
                    return [...new Set(this.variants.map(v => v.storage_gb).filter(Boolean))];
                },
                regions() {
                    return [...new Set(this.variants.map(v => v.region).filter(Boolean))];
                },
                currentImages() {
                    const variant = this.current();
                    return (variant.images && variant.images.length) ? variant.images : this.productImages;
                },
                discountPercent() {
                    const v = this.current();
                    if (!v.compare_at_price || v.compare_at_price <= v.price) return null;
                    return Math.round(((v.compare_at_price - v.price) / v.compare_at_price) * 100);
                },
                availabilityLabel() {
                    return {
                        in_stock: 'In Stock',
                        pre_order: 'Pre-Order',
                        out_of_stock: 'Out of Stock',
                        discontinued: 'Discontinued'
                    } [this.current().availability] ?? '';
                },
                formatPrice(cents) {
                    return '৳' + (cents / 100).toLocaleString();
                },
            }
        }
    </script>
@endpush
