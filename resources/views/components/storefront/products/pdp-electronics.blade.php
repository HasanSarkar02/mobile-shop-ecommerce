@props([
    'product',
    'translation' => null,
    'productDescription' => null,
    'specificationGroups' => null,
    'navSections' => null,
    'navLabels' => [],
    'policyLinks' => null,
    'shippingMethods' => null,
    'paymentMethods' => null,
    'relatedCards' => null,
    'crossSellCards' => null,
    'upsellCards' => null,
    'frequentlyBoughtCards' => null,
    'compatibleAccessoryCards' => null,
    'recentlyViewedCards' => null,
    'isWishlisted' => false,
    'isComparing' => false,
    'emiData' => [],
    'emiFromMonthly' => null,
    'emiHasZero' => false,
    'emiBasePrice' => 0,
    'showDescription' => false,
    'showWarranty' => false,
    'showFaqs' => false,
])

@php
    $colorMap = [];
    if ($product && $product->variants) {
        foreach ($product->variants as $variant) {
            if ($variant->color) {
                $colorMap[(string) $variant->color] = \App\Support\ColorHelper::resolveColorHex((string) $variant->color);
            }
            foreach ($variant->attributeValues as $val) {
                if ($val->attributeDefinition && ($val->attributeDefinition->code === 'color' || $val->attributeDefinition->code === 'colour')) {
                    $opt = $val->displayValue() ?? (string) ($val->attributeOption?->value ?? '');
                    if ($opt) {
                        $colorMap[$opt] = \App\Support\ColorHelper::resolveColorHex($opt);
                    }
                }
            }
        }
    }
@endphp
<script>
    window._productColorMap = @json($colorMap);
</script>

@php
    $translation = $translation ?? $product->translation() ?? $product->translation('en');
    $productDescription = $productDescription ?? optional($translation)->sanitizedDescription();
    $keyFeatures = collect($specificationGroups)->pluck('items')->flatten()->take(4);
    $tenantName = tenant()?->name ?? config('app.name', 'Store');
    $iconMap = [
        'chip' => 'cpu', 'processor' => 'cpu', 'cpu' => 'cpu', 'soc' => 'cpu',
        'camera' => 'camera', 'lens' => 'camera', 'photo' => 'camera',
        'battery' => 'battery', 'display' => 'monitor', 'screen' => 'monitor',
        'storage' => 'hard-drive', 'memory' => 'hard-drive', 'ram' => 'hard-drive',
        'material' => 'cube', 'titanium' => 'cube', 'design' => 'cube', 'build' => 'cube',
        'weight' => 'scale', 'color' => 'palette', 'warranty' => 'shield',
    ];
    $resolveIcon = function(string $code, string $label) use ($iconMap) {
        $k = strtolower($code.' '.$label);
        foreach ($iconMap as $needle => $icon) {
            if (str_contains($k, $needle)) return $icon;
        }
        return 'cube';
    };
@endphp

{{-- 3-Column Premium Layout: Gallery 5 | Info 4 | Delivery/Trust 3 — gallery dominant for electronics --}}
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
    {{-- Left: Gallery --}}
    <div class="lg:col-span-5">
        <x-storefront.gallery aspect="aspect-[4/3] lg:aspect-[1/1]" />
    </div>

    {{-- Middle: Product Info --}}
    <div class="lg:col-span-4" x-ref="buyBox">
        <div class="flex justify-between items-start gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-2 text-xs flex-wrap">
                    @if ($product->brand)
                        <span class="text-gray-500">{{ $product->brand->name }}</span>
                    @endif
                    @if ($product->is_official_import)
                        <span class="px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300 border border-blue-100 dark:border-blue-900 text-[11px]">{{ __('Official Product') }}</span>
                    @endif
                </div>
                <h1 class="text-2xl lg:text-[1.75rem] font-bold mt-2 leading-tight">{{ ($product->translation() ?? $product->translation('en'))?->name }}</h1>
                {{-- Social Proof — real data only --}}
                @php $soldQty = (float) ($product->sold_quantity ?? 0); @endphp
                <div class="mt-2 flex items-center gap-2 text-sm flex-wrap">
                    @if ($product->reviews_count > 0 && $product->average_rating !== null)
                        <span class="flex items-center gap-1">
                            <x-ui.rating-stars :rating="$product->average_rating" :count="null" />
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ number_format((float) $product->average_rating, 1) }}</span>
                            <span class="text-gray-500 dark:text-gray-400">({{ $product->reviews_count }} {{ Str::plural('review', $product->reviews_count) }})</span>
                        </span>
                    @else
                        <span class="text-gray-400 text-xs">{{ __('No reviews yet') }}</span>
                    @endif
                    @if ($soldQty > 0)
                        <span class="text-gray-300">|</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ rtrim(rtrim(number_format($soldQty, 3, '.', ','), '0'), '.') }} sold</span>
                    @endif
                </div>
            </div>
        <div class="flex gap-2 flex-shrink-0">
                <x-storefront.wishlist-button :id="$product->id" :wishlisted="$isWishlisted ?? false" variant="pdp" />
            </div>
        </div>

        {{-- Key Features Boxes (top 4 specs) --}}
        @if ($keyFeatures->isNotEmpty())
            <div class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-2">
                @foreach ($keyFeatures as $feature)
                    @php $icon = $resolveIcon($feature->attributeDefinition->code ?? '', $feature->attributeDefinition->label ?? ''); @endphp
                    <div class="flex items-center gap-2 p-2 rounded-lg bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-800">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 text-gray-500 flex-shrink-0">
                            <x-ui.icon :name="$icon" class="w-4 h-4" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 line-clamp-1">{{ $feature->attributeDefinition->label }}</p>
                            <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 line-clamp-1" title="{{ $feature->displayValue() }}">{{ $feature->displayValue() }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <template x-if="current()">
            <div>
                <div class="mt-4 flex items-baseline gap-2 flex-wrap">
                    <span class="font-bold text-2xl lg:text-3xl" x-text="formatPrice(current().price)"></span>
                    <template x-if="current().compare_at_price && current().compare_at_price > current().price">
                        <span class="text-gray-400 line-through text-sm" x-text="formatPrice(current().compare_at_price)"></span>
                    </template>
                    <template x-if="discountPercent()">
                        <span class="px-2 py-0.5 rounded bg-red-100 dark:bg-red-950 text-red-600 dark:text-red-400 text-xs font-bold" x-text="discountPercent() + '% OFF'"></span>
                    </template>
                </div>
                @if ($product->emiPlans->isNotEmpty())
                    <p class="text-xs mt-1 text-gray-500">{{ __('EMI starts from') }} <span x-text="emiHeadline()">{{ money((int) ($emiFromMonthly ?? 0)) }}/month</span></p>
                @endif
            </div>
        </template>
        <template x-if="!current() && priceRange()">
            <div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="font-bold text-2xl" x-text="priceRangeLabel()"></span>
                </div>
                <p class="text-xs mt-1 text-gray-500">{{ __('Select options to see exact price') }}</p>
            </div>
        </template>

        <template x-if="selectionIssueType()">
            <div class="mt-3 rounded-xl border p-3 text-sm" :class="selectionIssueType() === 'invalid' ? 'border-red-200 bg-red-50 text-red-700' : 'border-gray-200 bg-gray-50 text-gray-600'">
                <span x-text="selectionMessage()"></span>
            </div>
        </template>

        {{-- Variant Selector --}}
        <div class="mt-5">
            <x-storefront.variant-selector use-color-swatches="true" />
        </div>

        <div class="flex items-center gap-3 mt-6">
            <div class="flex items-center border border-gray-300 dark:border-gray-700 rounded-xl">
                <button @click="quantity = Math.max(sellByUnit, parseFloat((quantity - sellByUnit).toFixed(3)))" class="w-10 h-11 flex items-center justify-center text-lg" aria-label="Decrease quantity">−</button>
                <input type="number" x-model.number="quantity" :step="sellByUnit" :min="sellByUnit" step="{{ $product->sell_by_unit ?? 1 }}" class="w-16 text-center bg-transparent focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                <button @click="quantity = Math.min(parseFloat((quantity + sellByUnit).toFixed(3)), (current()?.available_quantity ?? 0) > 0 && current().purchase_state !== 'preorder' && current().purchase_state !== 'dropship' ? current().available_quantity : 99)" class="w-10 h-11 flex items-center justify-center text-lg" aria-label="Increase quantity">+</button>
            </div>
            <div class="flex-1 text-sm">
                <p class="text-sm font-medium" :class="availabilityTone()" x-text="availabilityLabel()"></p>
                {{-- Decision 13/14: show exact count only when low_stock (≤5), otherwise only "In Stock" via availabilityLabel() --}}
                <template x-if="current() && current().purchase_state === 'low_stock' && parseFloat(current().available_quantity_decimal ?? current().available_quantity) > 0">
                    <p class="text-xs mt-0.5 text-amber-600" x-text="'Only ' + parseFloat(current().available_quantity_decimal ?? current().available_quantity) + ' left in stock'"></p>
                </template>
                <template x-if="restockMessage()">
                    <p class="text-xs text-gray-500" x-text="restockMessage()"></p>
                </template>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mt-4">
            <x-storefront.add-to-cart-button type="cart" />
            <x-storefront.add-to-cart-button type="buy-now" />
        </div>

        <div class="mt-3 flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
            <button type="button" @click="toggleCompare()" class="flex items-center gap-1.5 hover:text-[var(--brand)]" :class="comparing ? 'text-[var(--brand)]' : ''">
                <x-ui.icon name="compare" class="w-4 h-4" /> <span x-text="comparing ? &quot;{{ __('Added to Compare') }}&quot; : &quot;{{ __('Add to Compare') }}&quot;"></span>
            </button>
            <button type="button" @click="share()" class="flex items-center gap-1.5 hover:text-[var(--brand)]">
                <x-ui.icon name="share" class="w-4 h-4" /> {{ __('Share this product') }}
            </button>
        </div>
        @php
            $waNumber = tenant()?->themeSettings?->social_links['whatsapp'] ?? null;
            $waTranslation = $product->translation() ?? $product->translation('en');
            $waProductName = $waTranslation?->name ?? $product->name ?? '';
            $waUrl = \App\Support\WhatsApp::url(is_string($waNumber) ? $waNumber : null, 'Hi, I am interested in '.$waProductName.' ('.url()->current().')');
        @endphp
        @if ($waUrl)
            <div class="mt-4">
                <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-xl border border-[#25D366] px-4 py-2 text-sm font-medium text-[#128C7E] transition hover:bg-[#25D366]/5">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="h-5 w-5" aria-hidden="true">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                    </svg>
                    {{ __('Ask about this product on WhatsApp') }}
                </a>
            </div>
        @endif
    </div>

    {{-- Right: Delivery & Trust --}}
    <div class="lg:col-span-3 space-y-4">
        <div class="rounded-2xl border border-gray-100 dark:border-gray-800 p-4 bg-white dark:bg-gray-900">
            <h3 class="font-semibold text-sm flex items-center gap-2">
                <x-ui.icon name="truck" class="w-4 h-4" /> {{ __('Delivery Information') }}
            </h3>
            @if ($shippingMethods && $shippingMethods->isNotEmpty())
                <div class="mt-3 space-y-2.5 text-xs">
                    @foreach ($shippingMethods as $method)
                        <div class="flex justify-between gap-4">
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $method->name }}</span>
                            <span class="font-medium">
                                @if ($method->type === \App\Enums\ShippingMethodType::Free || (int) $method->cost === 0)
                                    {{ __('Free Delivery') }}
                                @else
                                    {{ money((int) $method->cost) }}
                                @endif
                            </span>
                        </div>
                    @endforeach
                    <p class="text-[11px] text-gray-400 pt-2 border-t border-gray-100 dark:border-gray-800">{{ __('Exact delivery charge calculated at checkout based on your address.') }}</p>
                </div>
            @else
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">{{ __('Delivery charges calculated at checkout.') }}</p>
            @endif
        </div>

        <div class="rounded-2xl border border-gray-100 dark:border-gray-800 p-4 bg-white dark:bg-gray-900">
            <h3 class="font-semibold text-sm flex items-center gap-2">
                <x-ui.icon name="shield" class="w-4 h-4 text-green-600" /> {{ __('Why :name?', ['name' => $tenantName]) }}
            </h3>
            <ul class="mt-3 space-y-2.5 text-xs">
                <li class="flex gap-2">
                    <x-ui.icon name="shield" class="w-4 h-4 text-green-600 flex-shrink-0" />
                    <div>
                        <p class="font-medium">{{ __('100% Authentic') }}</p>
                        <p class="text-gray-500">{{ __('Sourced from authorized partners') }}</p>
                    </div>
                </li>
                <li class="flex gap-2">
                    <x-ui.icon name="truck" class="w-4 h-4 text-amber-600 flex-shrink-0" />
                    <div>
                        <p class="font-medium">{{ __('7 Days Easy Returns') }}</p>
                        <p class="text-gray-500">{{ __('No questions asked') }}</p>
                    </div>
                </li>
                <li class="flex gap-2">
                    <x-ui.icon name="lock" class="w-4 h-4 text-blue-600 flex-shrink-0" />
                    <div>
                        <p class="font-medium">{{ __('Secure Payments') }}</p>
                        <p class="text-gray-500">{{ __('SSL encrypted & safe') }}</p>
                    </div>
                </li>
            </ul>
        </div>

        @if ($frequentlyBoughtCards && $frequentlyBoughtCards->isNotEmpty())
            <div class="rounded-2xl border border-gray-100 dark:border-gray-800 p-4 bg-white dark:bg-gray-900">
                <h3 class="font-semibold text-sm">{{ __('Frequently Bought Together') }}</h3>
                <div class="mt-3 flex items-center gap-2">
                    @foreach ($frequentlyBoughtCards->take(3) as $card)
                        <div class="w-20 h-20 rounded-xl border border-gray-100 dark:border-gray-800 overflow-hidden bg-white flex items-center justify-center p-1">
                            @if ($card['has_image'])
                                <img src="{{ $card['image'] }}" alt="{{ $card['name'] }}" class="max-w-full max-h-full object-contain" />
                            @else
                                <x-ui.icon name="image" class="w-6 h-6 text-gray-300" />
                            @endif
                        </div>
                        @if (!$loop->last)
                            <span class="text-gray-400">+</span>
                        @endif
                    @endforeach
                </div>
                @php
                    $fbtTotal = $frequentlyBoughtCards->sum(fn($c) => $c['variant']['price'] ?? 0);
                    $fbtCompare = $frequentlyBoughtCards->sum(fn($c) => $c['variant']['compare_at_price'] ?? $c['variant']['price'] ?? 0);
                    $fbtSaving = $fbtCompare - $fbtTotal;
                @endphp
                <div class="mt-3 flex items-baseline gap-2 flex-wrap">
                    <span class="font-bold text-sm">{{ __('Total:') }} {{ money_without_trailing_zeros((int) $fbtTotal) }}</span>
                    @if ($fbtSaving > 0)
                        <span class="text-xs line-through text-gray-400">{{ money_without_trailing_zeros((int) $fbtCompare) }}</span>
                        <span class="text-xs px-1.5 py-0.5 rounded bg-red-100 dark:bg-red-950 text-red-600 text-[11px]">{{ __('SAVE') }} {{ money_without_trailing_zeros((int) $fbtSaving) }}</span>
                    @endif
                </div>
                <button type="button"
                    x-data="{ adding:false }"
                    @click="adding=true; (async()=>{ for(const c of @js($frequentlyBoughtCards->map(fn($c)=>['variant_id'=>$c['variant']['id'] ?? null])->filter(fn($x)=>$x['variant_id']))){ if(c.variant_id) await $store.cart.add(c.variant_id, 1); } adding=false; $dispatch('toast',{detail:{message:'Bundle added to cart',type:'success'}}); })()"
                    class="mt-3 w-full rounded-xl border border-[var(--brand)] text-[var(--brand)] py-2 text-sm font-medium hover:bg-[var(--brand)] hover:text-white transition flex items-center justify-center gap-2"
                    :disabled="adding"
                >
                    <span x-show="!adding">{{ __('Add All to Cart') }}</span>
                    <span x-show="adding" class="flex items-center gap-1"><svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg> {{ __('Adding...') }}</span>
                </button>
            </div>
        @endif
    </div>
</div>

{{-- Below-the-fold Tabs — config-driven, no Overview --}}
@if ($navSections && $navSections->isNotEmpty())
    <nav x-data="productSections(@js($navSections))" x-init="init()" aria-label="Product sections" class="sticky top-16 lg:top-28 z-30 -mx-4 sm:mx-0 mt-8 border-y border-gray-200 dark:border-gray-800 bg-white/95 dark:bg-gray-950/95 backdrop-blur">
        <div class="flex items-center gap-1 overflow-x-auto px-4 sm:px-0 py-2">
            @foreach ($navSections as $sectionId)
                <a href="#{{ $sectionId }}" class="flex-shrink-0 px-3 py-1.5 rounded-full text-sm font-medium transition" :class="isActive('{{ $sectionId }}') ? 'text-[var(--brand)] bg-[var(--brand)]/10 font-semibold' : 'text-gray-600 dark:text-gray-300 hover:text-[var(--brand)] hover:bg-[var(--brand)]/10'" :aria-current="isActive('{{ $sectionId }}') ? 'location' : null">
                    {{ $navLabels[$sectionId] ?? ucfirst($sectionId) }} @if($sectionId==='reviews') ({{ $product->reviews_count }}) @endif
                </a>
            @endforeach
        </div>
    </nav>
@endif

{{-- Sections in priority order --}}
@foreach ($navSections as $sectionId)
    @switch($sectionId)
        @case('specifications')
            <section id="specifications" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10">
                <h2 class="text-xl font-bold">Specifications</h2>
                <div class="mt-5">
                    @forelse($specificationGroups as $group)
                        <div class="mb-6">
                            <h3 class="text-sm font-semibold text-gray-500 mb-2">{{ $group['group'] }}</h3>
                            <dl class="divide-y divide-gray-100 dark:divide-gray-800 rounded-xl border border-gray-100 dark:border-gray-800">
                                @foreach ($group['items'] as $value)
                                    <div class="flex py-2.5 text-sm gap-4 px-4">
                                        <dt class="w-1/3 text-gray-500">{{ $value->attributeDefinition->label }}</dt>
                                        <dd class="text-gray-800 dark:text-gray-200">{{ $value->displayValue() }}@if($value->attributeDefinition->unit) <span class="text-gray-500">{{ $value->attributeDefinition->unit }}</span>@endif</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No specifications listed yet.</p>
                    @endforelse
                </div>
            </section>
            @break
        @case('description')
            @if ($showDescription)
                <section id="description" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10" aria-labelledby="description-heading">
                    <h2 id="description-heading" class="text-xl font-bold">Description</h2>
                    <div class="prose dark:prose-invert max-w-none mt-5">
                        {!! $productDescription !!}
                    </div>
                </section>
            @endif
            @break
        @case('warranty')
            @if ($showWarranty)
                <section id="warranty" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10" aria-labelledby="warranty-heading">
                    <h2 id="warranty-heading" class="text-xl font-bold">Warranty</h2>
                    <div class="prose dark:prose-invert max-w-none mt-5">
                        {!! nl2br(e(($product->translation() ?? $product->translation('en'))->warranty_info)) !!}
                    </div>
                </section>
            @endif
            @break
        @case('reviews')
            <section id="reviews" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10" aria-labelledby="reviews-heading">
                <h2 id="reviews-heading" class="text-xl font-bold">Reviews ({{ $product->reviews_count }})</h2>
                @if ($product->reviews_count > 0)
                    <div class="mt-3"><x-ui.rating-stars :rating="$product->average_rating" :count="$product->reviews_count" /></div>
                @endif
                <div class="space-y-4 mt-5">
                    @forelse($product->approvedReviews as $review)
                        <div class="border border-gray-100 dark:border-gray-800 rounded-xl p-4">
                            <div class="flex justify-between items-start">
                                <p class="font-medium">{{ $review->customer->name }}</p>
                                @if ($review->is_verified_purchase)
                                    <x-ui.badge variant="success">Verified Purchase</x-ui.badge>
                                @endif
                            </div>
                            <x-ui.rating-stars :rating="$review->rating" />
                            @if ($review->title)
                                <p class="font-medium mt-2 text-sm">{{ $review->title }}</p>
                            @endif
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $review->body }}</p>
                            @if (filled($review->reply_text))
                                <div class="mt-3 ml-3 pl-4 border-l-2 border-[var(--brand)]/30 bg-gray-50 dark:bg-gray-800/50 rounded-r-lg p-3">
                                    <p class="text-xs font-semibold mb-1">Store Reply @if($review->replied_at) — {{ $review->replied_at->format('M j, Y') }}@endif</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $review->reply_text }}</p>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No reviews yet — be the first to review this product.</p>
                    @endforelse
                </div>
                @auth('customer')
                    <form method="POST" action="{{ route('storefront.product.reviews.store', $product) }}" class="mt-6 space-y-3 max-w-lg">
                        @csrf
                        <x-ui.select name="rating" label="Rating" required>
                            <option value="">Select a rating</option>
                            @for ($i = 5; $i >= 1; $i--)
                                <option value="{{ $i }}">{{ $i }} Stars</option>
                            @endfor
                        </x-ui.select>
                        <x-ui.input name="title" label="Title (optional)" />
                        <x-ui.textarea name="body" label="Your review" required />
                        <x-ui.button type="submit" variant="primary">Submit Review</x-ui.button>
                    </form>
                @else
                    <p class="text-sm text-gray-500 mt-6"><a href="{{ route('storefront.login') }}" class="text-[var(--brand)] font-medium">Log in</a> to write a review.</p>
                @endauth
            </section>
            @break
        @case('faq')
            @if ($showFaqs)
                <section id="faq" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10" aria-labelledby="faq-heading">
                    <h2 id="faq-heading" class="text-xl font-bold">FAQ</h2>
                    @if ($product->faqs->isNotEmpty())
                        <div class="mt-5 space-y-3">
                            @foreach ($product->faqs as $faq)
                                <div x-data="{ expanded: false }" class="border border-gray-200 dark:border-gray-800 rounded-xl p-4">
                                    <button @click="expanded = !expanded" class="w-full text-left font-medium flex justify-between items-center">
                                        {{ $faq->question }}
                                        <span x-text="expanded ? '−' : '+'" class="text-gray-400"></span>
                                    </button>
                                    <div x-show="expanded" x-collapse class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $faq->answer }}</div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-400 mt-5">No FAQs available for this product yet.</p>
                    @endif
                </section>
            @endif
            @break
    @endswitch
@endforeach

@if ($relatedCards && $relatedCards->isNotEmpty())
    <section id="related" class="scroll-mt-32 mt-10">
        <h2 class="text-xl font-bold">You May Also Like</h2>
        <div class="grid {{ \App\Support\IndustryConfig::currentGet('ui.grid_class', 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4') }} gap-4 mt-5">
            @foreach ($relatedCards as $card)
                <x-dynamic-component :component="\App\Support\IndustryConfig::currentGet('ui.card_component', 'storefront.product-cards.default')" :card="$card" />
            @endforeach
        </div>
    </section>
@endif
@if ($crossSellCards && $crossSellCards->isNotEmpty())
    <section id="cross-sells" class="scroll-mt-32 mt-10">
        <h2 class="text-xl font-bold">Complete Your Setup</h2>
        <div class="grid {{ \App\Support\IndustryConfig::currentGet('ui.grid_class', 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4') }} gap-4 mt-5">
            @foreach ($crossSellCards as $card)
                <x-dynamic-component :component="\App\Support\IndustryConfig::currentGet('ui.card_component', 'storefront.product-cards.default')" :card="$card" />
            @endforeach
        </div>
    </section>
@endif
@if ($frequentlyBoughtCards && $frequentlyBoughtCards->isNotEmpty() && $frequentlyBoughtCards->count() > 3)
    {{-- FBT already shown in right rail for first 3; show remaining as grid if >3 --}}
    <section id="frequently-bought-extra" class="scroll-mt-32 mt-10">
        <h2 class="text-xl font-bold">Frequently Bought Together</h2>
        <div class="grid {{ \App\Support\IndustryConfig::currentGet('ui.grid_class', 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4') }} gap-4 mt-5">
            @foreach ($frequentlyBoughtCards as $card)
                <x-dynamic-component :component="\App\Support\IndustryConfig::currentGet('ui.card_component', 'storefront.product-cards.default')" :card="$card" />
            @endforeach
        </div>
    </section>
@endif

@if ($recentlyViewedCards && $recentlyViewedCards->isNotEmpty())
    <section id="recently-viewed" class="scroll-mt-32 mt-10">
        <h2 class="text-xl font-bold">Recently Viewed</h2>
        <div class="mt-5 -mx-4 sm:mx-0">
            <div class="flex gap-4 overflow-x-auto px-4 sm:px-0 pb-2 snap-x">
                @foreach ($recentlyViewedCards as $card)
                    <div class="w-44 flex-shrink-0 snap-start">
                        <x-dynamic-component :component="\App\Support\IndustryConfig::currentGet('ui.card_component', 'storefront.product-cards.default')" :card="$card" />
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif

<div x-ref="pdpEnd" class="h-px" aria-hidden="true"></div>
