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
    $translation = $translation ?? $product->translation() ?? $product->translation('en');
    $productDescription = $productDescription ?? optional($translation)->sanitizedDescription();
    $isGrocery = true;
@endphp

{{-- Grocery Premium — Fresh, Weight-First, Instant Clarity --}}
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
    {{-- Left: Gallery — slightly larger for tactile produce --}}
    <div class="lg:col-span-6">
        <div class="sticky top-6">
            <x-storefront.gallery aspect="aspect-square" />
            <div class="mt-3 hidden lg:flex items-center justify-center gap-2 text-xs text-gray-500">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900 px-2.5 py-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Fresh handpicked today
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 dark:bg-amber-950/30 border border-amber-100 dark:border-amber-900 px-2.5 py-1">
                    Same-day • Dhaka metro
                </span>
            </div>
        </div>
    </div>

    {{-- Right: Buy Stack --}}
    <div class="lg:col-span-6 xl:col-span-5 xl:col-start-7" x-ref="buyBox">
        {{-- Brand + freshness --}}
        <div class="flex items-center gap-2 flex-wrap">
            @if ($product->brand)
                <span class="text-sm font-medium text-gray-500">{{ $product->brand->name }}</span>
                <span class="w-1 h-1 rounded-full bg-gray-300"></span>
            @endif
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500 text-white px-2.5 py-1 text-xs font-bold">
                <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span> FRESH
            </span>
            @if ($product->is_official_import)
                <span class="rounded-full bg-gray-900 text-white dark:bg-white dark:text-gray-900 px-2 py-1 text-xs font-semibold">Official</span>
            @endif
        </div>

        <h1 class="text-2xl lg:text-3xl font-bold tracking-tight mt-2 leading-tight">{{ ($product->translation() ?? $product->translation('en'))?->name }}</h1>

        @php $soldQty = (float) ($product->sold_quantity ?? 0); @endphp
        <div class="mt-2 flex items-center gap-3 text-sm">
            @if ($product->reviews_count > 0 && $product->average_rating !== null)
                <span class="flex items-center gap-1.5">
                    <x-ui.rating-stars :rating="$product->average_rating" :count="null" />
                    <span class="font-semibold text-gray-700 dark:text-gray-300">{{ number_format((float) $product->average_rating, 1) }}</span>
                    <span class="text-gray-500">({{ $product->reviews_count }})</span>
                </span>
                @if ($soldQty > 0)
                    <span class="text-gray-300">•</span>
                    <span class="text-gray-500">{{ rtrim(rtrim(number_format($soldQty, 3, '.', ','), '0'), '.') }} sold</span>
                @endif
            @else
                <span class="text-xs text-gray-400">No reviews yet — be first</span>
                @if ($soldQty > 0)
                    <span class="text-gray-500">{{ rtrim(rtrim(number_format($soldQty, 3, '.', ','), '0'), '.') }} sold</span>
                @endif
            @endif
            <div class="ml-auto flex gap-1.5">
                <x-storefront.wishlist-button :id="$product->id" :wishlisted="$isWishlisted" variant="pdp" />
                <button @click="toggleCompare()" :disabled="compareLoading" class="w-9 h-9 rounded-xl border flex items-center justify-center" :class="comparing ? 'border-[var(--brand)] bg-[var(--brand)]/10 text-[var(--brand)]' : 'border-gray-200 dark:border-gray-700'" aria-label="Compare"><x-ui.icon name="grid" class="w-4 h-4" /></button>
                <button @click="share()" class="w-9 h-9 rounded-xl border border-gray-200 dark:border-gray-700 flex items-center justify-center" aria-label="Share"><x-ui.icon name="share" class="w-4 h-4" /></button>
            </div>
        </div>

        {{-- Origin / freshness meta --}}
        @if ($product->uom)
            <div class="mt-4 flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 dark:bg-amber-950/30 border border-amber-100 dark:border-amber-900 px-3 py-1.5 text-xs font-medium text-amber-800 dark:text-amber-200">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c-4.5 1.5-7.5 5.25-7.5 9.75 0 4.5 3 8.25 7.5 9.75 4.5-1.5 7.5-5.25 7.5-9.75C19.5 8.25 16.5 4.5 12 3z"/></svg>
                    Sold by {{ $product->uom->name }} • {{ $product->sell_by_unit ? rtrim(rtrim(number_format((float)$product->sell_by_unit,3,'.',''), '0'), '.').' '.$product->uom->code : 'per unit' }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> In stock • Ships today
                </span>
            </div>
        @endif

        {{-- Price — per unit hero --}}
        <template x-if="current()">
            <div class="mt-5 rounded-2xl bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800 p-4">
                <div class="flex items-baseline gap-2 flex-wrap">
                    <span class="text-3xl font-extrabold tracking-tight" x-text="formatPrice(current().price)"></span>
                    <template x-if="current().compare_at_price && current().compare_at_price > current().price">
                        <span class="text-sm text-gray-400 line-through" x-text="formatPrice(current().compare_at_price)"></span>
                    </template>
                    <template x-if="discountPercent()">
                        <span class="ml-1 rounded-full bg-red-500 text-white px-2 py-0.5 text-xs font-bold" x-text="discountPercent() + '% OFF'"></span>
                    </template>
                    <span class="ml-auto text-xs font-medium text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900 rounded-full px-2 py-1" x-text="availabilityLabel()"></span>
                </div>
                <p class="mt-1 text-xs text-gray-500">
                    {{-- Decision 13/14: In Stock shows only via availabilityLabel(); exact count only when low_stock (≤5) --}}
                    <template x-if="current() && current().purchase_state === 'low_stock' && parseFloat(current().available_quantity_decimal ?? current().available_quantity) > 0">
                        <span x-text="'Only ' + parseFloat(current().available_quantity_decimal ?? current().available_quantity) + ' ' + (document.documentElement.lang==='bn' ? 'স্টকে আছে' : 'left in stock')" class="text-amber-600"></span>
                    </template>
                    <span x-show="restockMessage()" class="ml-2" x-text="restockMessage()"></span>
                </p>
                {{-- Per-kg / per-unit clarifier for grocery --}}
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    @if ($product->sell_by_unit && $product->uom)
                        {{ __('per') }} {{ rtrim(rtrim(number_format((float)$product->sell_by_unit,3,'.',''), '0'), '.') }} {{ $product->uom->name }} • {{ __('minimum') }} {{ rtrim(rtrim(number_format((float)$product->sell_by_unit,3,'.',''), '0'), '.') }} {{ $product->uom->code }}
                    @endif
                    • {{ __('Free Delivery') }} on first order over ৳500
                </p>
            </div>
        </template>

        <template x-if="!current() && priceRange()">
            <div class="mt-5 rounded-2xl bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-800 p-4">
                <span class="text-3xl font-extrabold" x-text="priceRangeLabel()"></span>
                <p class="text-xs mt-1 text-gray-500">{{ __('Select options to see exact price') }}</p>
            </div>
        </template>

        <template x-if="selectionIssueType()">
            <div class="mt-4 rounded-xl border p-3 text-sm" :class="selectionIssueType() === 'invalid' ? 'border-red-200 bg-red-50 text-red-700' : 'border-amber-200 bg-amber-50 text-amber-700'">
                <span x-text="selectionMessage()"></span>
            </div>
        </template>

        {{-- Variant selector — weight-centric for grocery --}}
        <div class="mt-6">
            <x-storefront.variant-selector />
        </div>

        {{-- Quantity — large, thumb-friendly for grocery --}}
        <div class="mt-6 flex items-center gap-4">
            <div class="flex items-center rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
                <button @click="quantity = Math.max(sellByUnit, parseFloat((quantity - sellByUnit).toFixed(3)))" class="w-11 h-11 flex items-center justify-center text-xl font-light hover:bg-gray-50 dark:hover:bg-gray-800" aria-label="Decrease">−</button>
                <input type="number" x-model.number="quantity" :step="sellByUnit" :min="sellByUnit" class="w-20 text-center bg-transparent font-semibold focus:outline-none [appearance:textfield]" />
                <button @click="quantity = Math.min(parseFloat((quantity + sellByUnit).toFixed(3)), (current()?.available_quantity ?? 0) > 0 ? current().available_quantity : 99)" class="w-11 h-11 flex items-center justify-center text-xl font-light hover:bg-gray-50 dark:hover:bg-gray-800" aria-label="Increase">+</button>
            </div>
            <div class="text-xs leading-tight">
                <p class="font-semibold text-gray-900 dark:text-white" x-text="quantity + ' × ' + sellByUnit + ' ' + (document.documentElement.lang==='bn' ? 'পরিমাণ' : 'qty')"></p>
                <p class="text-gray-500" x-text="availabilityTone().includes('green') ? 'Fresh • Ships today' : availabilityLabel()"></p>
            </div>
        </div>

        {{-- CTAs — grocery: Add to Cart primary, Buy Now secondary with trust --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-6">
            <x-storefront.add-to-cart-button type="cart" />
            <x-storefront.add-to-cart-button type="buy-now" />
        </div>
        <p class="mt-2 text-center text-xs text-gray-400">Cash on delivery • Card • Mobile banking</p>

        {{-- Trust strip — grocery specific --}}
        <div class="mt-6 grid grid-cols-3 gap-3 text-center">
            <div class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-3">
                <p class="text-[11px] font-bold tracking-wide uppercase text-emerald-600">Fresh</p>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Handpicked daily</p>
            </div>
            <div class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-3">
                <p class="text-[11px] font-bold tracking-wide uppercase text-sky-600">Delivery</p>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">Dhaka metro</p>
            </div>
            <div class="rounded-2xl bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 p-3">
                <p class="text-[11px] font-bold tracking-wide uppercase text-amber-600">Returns</p>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">No questions</p>
            </div>
        </div>

        {{-- Delivery / payment compact --}}
        @if ($shippingMethods->isNotEmpty())
            <div class="mt-6 rounded-2xl border border-emerald-100 dark:border-emerald-900/30 bg-emerald-50/50 dark:bg-emerald-950/20 p-4">
                <p class="text-sm font-bold text-emerald-800 dark:text-emerald-200 flex items-center gap-2">
                    <x-ui.icon name="truck" class="w-4 h-4" /> {{ __('Delivery Information') }}
                </p>
                <ul class="mt-2 space-y-1 text-xs text-emerald-700 dark:text-emerald-300">
                    @foreach ($shippingMethods->take(2) as $m)
                        <li class="flex justify-between"><span>{{ $m->name }}</span><span class="font-bold">{{ $m->cost==0 ? __('Free Delivery') : money((int)$m->cost) }}</span></li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs text-emerald-600/70">{{ __('Exact delivery charge calculated at checkout based on your address.') }}</p>
            </div>
        @endif
    </div>

    {{-- Right: compact trust + frequently bought (hidden on mobile, shown below) --}}
    <div class="hidden lg:block lg:col-span-12 xl:col-span-1"></div>
</div>

{{-- Below-fold: same as default but keep grocery tone --}}
@if ($navSections && $navSections->isNotEmpty())
    <nav x-data="productSections(@js($navSections))" x-init="init()" aria-label="Product sections" class="sticky top-16 lg:top-[101px] z-30 -mx-4 sm:mx-0 mt-8 border-y border-gray-200 dark:border-gray-800 bg-white/95 dark:bg-gray-950/95 backdrop-blur">
        <div class="flex items-center gap-1 overflow-x-auto px-4 sm:px-0 py-2">
            @foreach ($navSections as $sectionId)
                <a href="#{{ $sectionId }}" class="flex-shrink-0 px-3 py-1.5 rounded-full text-sm font-medium transition" :class="isActive('{{ $sectionId }}') ? 'text-[var(--brand)] bg-[var(--brand)]/10 font-semibold' : 'text-gray-600 dark:text-gray-300 hover:text-[var(--brand)] hover:bg-[var(--brand)]/10'" :aria-current="isActive('{{ $sectionId }}') ? 'location' : null">
                    {{ $navLabels[$sectionId] ?? ucfirst($sectionId) }} @if($sectionId==='reviews') ({{ $product->reviews_count }}) @endif
                </a>
            @endforeach
        </div>
    </nav>
@endif

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
                    <div class="prose dark:prose-invert max-w-none mt-5">{!! $productDescription !!}</div>
                </section>
            @endif
            @break
        @case('warranty')
            @if ($showWarranty)
                <section id="warranty" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10" aria-labelledby="warranty-heading">
                    <h2 id="warranty-heading" class="text-xl font-bold">Warranty</h2>
                    <div class="prose dark:prose-invert max-w-none mt-5">{!! nl2br(e(($product->translation() ?? $product->translation('en'))->warranty_info)) !!}</div>
                </section>
            @endif
            @break
        @case('reviews')
            <section id="reviews" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10" aria-labelledby="reviews-heading">
                <h2 id="reviews-heading" class="text-xl font-bold">Reviews ({{ $product->reviews_count }})</h2>
                <div class="mt-5">
                    @forelse($product->approvedReviews as $review)
                        <div class="border border-gray-100 dark:border-gray-800 rounded-xl p-4 mb-3">
                            <div class="flex justify-between items-start">
                                <p class="font-medium">{{ $review->customer->name }}</p>
                                @if ($review->is_verified_purchase)
                                    <x-ui.badge variant="success">Verified Purchase</x-ui.badge>
                                @endif
                            </div>
                            <x-ui.rating-stars :rating="$review->rating" />
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $review->body }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No reviews yet — be the first to review this product.</p>
                    @endforelse
                </div>
            </section>
            @break
        @case('faq')
            @if ($showFaqs && $product->faqs->isNotEmpty())
                <section id="faq" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10" aria-labelledby="faq-heading">
                    <h2 id="faq-heading" class="text-xl font-bold">FAQ</h2>
                    <div class="mt-5 space-y-3">
                        @foreach ($product->faqs as $faq)
                            <div x-data="{ expanded: false }" class="border border-gray-200 dark:border-gray-800 rounded-xl p-4">
                                <button @click="expanded = !expanded" class="w-full text-left font-medium flex justify-between items-center">{{ $faq->question }}<span x-text="expanded ? '−' : '+'"></span></button>
                                <div x-show="expanded" x-collapse class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $faq->answer }}</div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
            @break
    @endswitch
@endforeach

@if ($relatedCards && $relatedCards->isNotEmpty())
    <section class="scroll-mt-32 mt-10">
        <h2 class="text-xl font-bold">You May Also Like</h2>
        <div class="grid {{ \App\Support\IndustryConfig::currentGet('ui.grid_class', 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4') }} gap-4 mt-5">
            @foreach ($relatedCards as $card)
                <x-dynamic-component :component="\App\Support\IndustryConfig::currentGet('ui.card_component', 'storefront.product-cards.default')" :card="$card" />
            @endforeach
        </div>
    </section>
@endif
