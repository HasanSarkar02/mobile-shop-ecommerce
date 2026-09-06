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
@endphp

<div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
    {{-- Gallery — 7 columns --}}
    <div class="lg:col-span-7">
        <x-storefront.gallery :aspect="\App\Support\IndustryConfig::currentGet('ui.image_aspect', 'aspect-[3/4]')" />
    </div>

    {{-- Buy Box — 5 columns sticky --}}
    <div class="lg:col-span-5 lg:sticky lg:top-28 self-start" x-ref="buyBox">
        <div class="flex justify-between items-start">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    @if ($product->brand)
                        <p class="text-sm text-gray-500">{{ $product->brand->name }}</p>
                    @endif
                    @if ($product->is_official_import)
                        <x-ui.badge variant="neutral">Official Product</x-ui.badge>
                    @endif
                </div>
                <h1 class="text-2xl font-bold mt-1">{{ ($product->translation() ?? $product->translation('en'))?->name }}</h1>
                @php $soldQty = (float) ($product->sold_quantity ?? 0); @endphp
                @if ($product->reviews_count > 0 && $product->average_rating !== null)
                    <div class="mt-2 flex items-center gap-2 text-sm">
                        <x-ui.rating-stars :rating="$product->average_rating" :count="$product->reviews_count" />
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ number_format((float) $product->average_rating, 1) }}</span>
                        <span class="text-gray-500">({{ $product->reviews_count }})</span>
                        @if ($soldQty > 0)
                            <span class="text-gray-300">|</span>
                            <span class="text-gray-500">{{ rtrim(rtrim(number_format($soldQty, 3, '.', ','), '0'), '.') }} sold</span>
                        @endif
                    </div>
                @elseif ($soldQty > 0)
                    <div class="mt-2 text-sm text-gray-500">{{ rtrim(rtrim(number_format($soldQty, 3, '.', ','), '0'), '.') }} sold</div>
                @else
                    <div class="mt-2 text-xs text-gray-400">No reviews yet</div>
                @endif
            </div>
            <div class="flex gap-2 flex-shrink-0">
                <x-storefront.wishlist-button :id="$product->id" :wishlisted="$isWishlisted ?? false" variant="pdp" />
                <button @click="toggleCompare()" :disabled="compareLoading"
                    class="p-2.5 rounded-xl border transition text-sm"
                    :class="comparing ? 'border-[var(--brand)] bg-[var(--brand)]/10 text-[var(--brand)]' : 'border-gray-300 dark:border-gray-700'"
                    aria-label="Toggle compare" :aria-pressed="comparing">
                    <x-ui.icon name="grid" class="w-5 h-5" />
                </button>
                <button @click="share()" :disabled="shareLoading"
                    class="p-2.5 rounded-xl border transition text-sm border-gray-300 dark:border-gray-700"
                    aria-label="Share product">
                    <x-ui.icon name="share" class="w-5 h-5" x-show="!shareLoading" />
                    <svg x-show="shareLoading" class="w-5 h-5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </button>
            </div>
        </div>

        <template x-if="selectionIssueType()">
            <div class="mt-4 rounded-xl border p-3 text-sm"
                :class="selectionIssueType() === 'invalid' ? 'border-red-200 dark:border-red-900 bg-red-50 dark:bg-red-950 text-red-700 dark:text-red-300' : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900 text-gray-600 dark:text-gray-300'">
                <span x-text="selectionMessage()"></span>
            </div>
        </template>

        <template x-if="current()">
            <div>
                <div class="mt-4 flex items-baseline gap-2 flex-wrap">
                    <span class="font-bold text-3xl" x-text="formatPrice(current().price)"></span>
                    <template x-if="current().compare_at_price && current().compare_at_price > current().price">
                        <span class="text-gray-400 line-through text-sm" x-text="formatPrice(current().compare_at_price)"></span>
                    </template>
                    <template x-if="discountPercent()">
                        <x-ui.badge variant="danger"><span x-text="discountPercent() + '% OFF'"></span></x-ui.badge>
                    </template>
                </div>
                <p class="text-sm mt-2 font-medium" :class="availabilityTone()" x-text="availabilityLabel()"></p>
                <template x-if="current().purchase_state === 'low_stock' && parseFloat(current().available_quantity_decimal ?? current().available_quantity) > 0">
                    <p class="text-sm mt-0.5 text-amber-600 font-medium" x-text="'Only ' + parseFloat(current().available_quantity_decimal ?? current().available_quantity) + ' left in stock'"></p>
                </template>
                <template x-if="restockMessage()">
                    <p class="text-sm mt-0.5 text-gray-500" x-text="restockMessage()"></p>
                </template>
            </div>
        </template>

        <template x-if="!current() && priceRange()">
            <div>
                <div class="mt-4 flex items-baseline gap-2 flex-wrap">
                    <span class="font-bold text-3xl" x-text="priceRangeLabel()"></span>
                </div>
                <p class="text-sm mt-2 text-gray-500">Select options to see exact price</p>
            </div>
        </template>

        <x-storefront.variant-selector />

        @if ($product->emiPlans->isNotEmpty())
            <div class="mt-6 flex items-center justify-between gap-4 rounded-xl border border-gray-200 dark:border-gray-800 p-3">
                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm font-medium">
                        <span class="text-gray-900 dark:text-gray-100">EMI from</span>
                        <span class="font-bold text-gray-900 dark:text-gray-100 tabular-nums" x-text="emiHeadline()">{{ money((int) ($emiFromMonthly ?? 0)) }}/month</span>
                    </p>
                    @if ($emiHasZero)
                        <p class="mt-1 text-xs text-green-600 dark:text-green-400 font-medium">0% EMI available</p>
                    @endif
                </div>
                <button type="button" @click="openEmi()" aria-haspopup="dialog" class="flex-shrink-0 text-sm font-medium text-[var(--brand)] hover:underline underline-offset-4">
                    View plans
                </button>
            </div>
            <template x-teleport="body">
                <div x-show="emiOpen" x-cloak class="fixed inset-0 z-[80] bg-black/60 flex items-center justify-center p-4 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="emi-modal-title" @keydown.escape.window="closeEmi()" @click="closeEmi()">
                    <div class="relative w-full max-w-lg max-h-[85vh] flex flex-col rounded-2xl bg-white dark:bg-gray-900 shadow-2xl overflow-hidden" x-ref="emiPanel" @click.stop>
                        <header class="flex items-start justify-between gap-4 border-b border-gray-200 dark:border-gray-800 p-5">
                            <div>
                                <h2 id="emi-modal-title" class="text-lg font-bold text-gray-900 dark:text-gray-100">EMI Plans</h2>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    <span x-text="emiHeadline()">{{ money((int) ($emiFromMonthly ?? 0)) }}/month</span>
                                </p>
                            </div>
                            <button type="button" x-ref="emiClose" @click="closeEmi()" class="p-2 rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-[var(--brand)]" aria-label="Close EMI plans">
                                <x-ui.icon name="close" class="w-5 h-5" />
                            </button>
                        </header>
                        <div class="overflow-y-auto p-5 space-y-3">
                            @foreach ($product->emiPlans as $plan)
                                @php $planRate = (float) $plan->interest_rate; @endphp
                                <div class="flex items-center justify-between gap-4 rounded-xl border border-gray-100 dark:border-gray-800 p-4">
                                    <div class="min-w-0">
                                        <p class="flex flex-wrap items-center gap-x-2 gap-y-1 font-medium text-gray-900 dark:text-gray-100">
                                            {{ $plan->bank_name }}
                                            @if ($planRate === 0.0)
                                                <x-ui.badge variant="success">0% EMI</x-ui.badge>
                                            @endif
                                        </p>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            <span class="tabular-nums" x-text="formatPrice(emiMonthly((current()?.price ?? 0), {{ $planRate }}, {{ $plan->tenure_months }}))">{{ money((int) round(($emiBasePrice * (1 + $planRate / 100)) / $plan->tenure_months)) }}</span>/month for {{ $plan->tenure_months }} months
                                        </p>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $planRate }}% interest</p>
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 tabular-nums">
                                            <span x-text="formatPrice(emiMonthly((current()?.price ?? 0), {{ $planRate }}, {{ $plan->tenure_months }}) * {{ $plan->tenure_months }})">{{ money((int) (round(($emiBasePrice * (1 + $planRate / 100)) / $plan->tenure_months) * $plan->tenure_months)) }}</span> total
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </template>
        @endif

        <div class="flex items-center gap-3 mt-6">
            <div class="flex items-center border border-gray-300 dark:border-gray-700 rounded-xl">
                <button @click="quantity = Math.max(sellByUnit, parseFloat((quantity - sellByUnit).toFixed(3)))" class="w-10 h-11 flex items-center justify-center text-lg" aria-label="Decrease quantity">−</button>
                <input type="number" x-model.number="quantity" :step="sellByUnit" :min="sellByUnit" step="{{ $product->sell_by_unit ?? 1 }}" class="w-16 text-center bg-transparent focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                <button @click="quantity = Math.min(parseFloat((quantity + sellByUnit).toFixed(3)), (current()?.available_quantity ?? 0) > 0 && current().purchase_state !== 'preorder' && current().purchase_state !== 'dropship' ? current().available_quantity : 99)" class="w-10 h-11 flex items-center justify-center text-lg" aria-label="Increase quantity">+</button>
            </div>
            {{-- Decision 13/14: normal In Stock hidden, Only X left when low_stock (≤5) via availabilityLabel + gated template above --}}
        </div>

        <div class="grid grid-cols-2 gap-4 mt-6">
            <x-storefront.add-to-cart-button type="cart" />
            <x-storefront.add-to-cart-button type="buy-now" />
        </div>

        <div class="mt-4 grid grid-cols-3 gap-3 text-[11px] text-gray-600 dark:text-gray-400">
            <div class="flex items-center gap-1.5">
                <x-ui.icon name="shield" class="w-4 h-4 text-green-600" />
                <span>100% Authentic</span>
            </div>
            <div class="flex items-center gap-1.5">
                <x-ui.icon name="truck" class="w-4 h-4 text-blue-600" />
                <span>7 Days Easy Returns</span>
            </div>
            <div class="flex items-center gap-1.5">
                <x-ui.icon name="lock" class="w-4 h-4 text-gray-700" />
                <span>Secure Payment</span>
            </div>
        </div>
    </div>
</div>

{{-- Below sections — shared --}}
@if ($navSections->isNotEmpty())
    <nav x-data="productSections(@js($navSections))" x-init="init()" aria-label="Product sections" class="sticky top-16 lg:top-28 z-30 -mx-4 sm:mx-0 mt-8 lg:mt-12 border-y border-gray-200 dark:border-gray-800 bg-white/95 dark:bg-gray-950/95 backdrop-blur">
        <div class="flex items-center gap-1 overflow-x-auto px-4 sm:px-0 py-2">
            @foreach ($navSections as $sectionId)
                <a href="#{{ $sectionId }}" class="flex-shrink-0 px-3 py-1.5 rounded-full text-sm font-medium transition text-gray-600 dark:text-gray-300 hover:text-[var(--brand)] hover:bg-[var(--brand)]/10" :class="isActive('{{ $sectionId }}') ? 'text-[var(--brand)] bg-[var(--brand)]/10 font-semibold' : ''" :aria-current="isActive('{{ $sectionId }}') ? 'location' : null">
                    {{ $navLabels[$sectionId] ?? ucfirst($sectionId) }}
                </a>
            @endforeach
        </div>
    </nav>
@endif

@foreach ($navSections as $sectionId)
    @switch($sectionId)
        @case('specifications')
            <section id="specifications" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16" aria-labelledby="specifications-heading">
                <h2 id="specifications-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Specifications</h2>
                <div class="mt-5">
                    @forelse($specificationGroups as $group)
                        <div class="mb-8 last:mb-0">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">
                                {{ $group['group'] }}
                            </h3>
                            <dl class="divide-y divide-gray-100 dark:divide-gray-800 rounded-xl border border-gray-100 dark:border-gray-800">
                                @foreach ($group['items'] as $value)
                                    <div class="flex py-2.5 text-sm gap-4 px-4">
                                        <dt class="w-1/3 flex-shrink-0 text-gray-500">{{ $value->attributeDefinition->label }}
                                        </dt>
                                        <dd class="text-gray-800 dark:text-gray-200">{{ $value->displayValue() }}
                                            @if ($value->attributeDefinition->unit)
                                                <span class="text-gray-500">{{ $value->attributeDefinition->unit }}</span>
                                            @endif
                                        </dd>
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
                <section id="description" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16" aria-labelledby="description-heading">
                    <h2 id="description-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Description</h2>
                    <div class="prose dark:prose-invert max-w-none mt-5">
                        {!! $productDescription !!}
                    </div>
                </section>
            @endif
            @break
        @case('warranty')
            @if ($showWarranty)
                <section id="warranty" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16" aria-labelledby="warranty-heading">
                    <h2 id="warranty-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Warranty</h2>
                    <div class="prose dark:prose-invert max-w-none mt-5">
                        {!! nl2br(e(($product->translation() ?? $product->translation('en'))->warranty_info)) !!}
                    </div>
                    @if ($warrantyPolicyLink)
                        <div class="mt-5">
                            <a href="{{ route('storefront.page', $warrantyPolicyLink['slug']) }}" class="text-sm font-medium text-[var(--brand)] hover:underline">
                                View Warranty Policy &rarr;
                            </a>
                        </div>
                    @endif
                </section>
            @endif
            @break
        @case('reviews')
            <section id="reviews" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16" aria-labelledby="reviews-heading">
                <h2 id="reviews-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Reviews ({{ $product->reviews_count }})</h2>
                @if ($product->reviews_count > 0)
                    <div class="mb-6 mt-5"><x-ui.rating-stars :rating="$product->average_rating" :count="$product->reviews_count" /></div>
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
                                <p class="font-medium mt-2">{{ $review->title }}</p>
                            @endif
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $review->body }}</p>
                            @if (filled($review->reply_text))
                                <div class="mt-3 ml-3 pl-4 border-l-2 border-[var(--brand)]/30 bg-gray-50 dark:bg-gray-800/50 rounded-r-lg p-3">
                                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1">Store Reply @if($review->replied_at)<span class="font-normal text-gray-500">— {{ $review->replied_at->format('M j, Y') }}</span>@endif</p>
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
                <section id="faq" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16" aria-labelledby="faq-heading">
                    <h2 id="faq-heading" class="text-xl lg:text-2xl font-bold tracking-tight">FAQ</h2>
                    @if ($product->faqs->isNotEmpty())
                        <div class="mt-5 space-y-3">
                            @foreach ($product->faqs as $faq)
                                <div x-data="{ expanded: false }" class="border border-gray-200 dark:border-gray-800 rounded-xl p-4">
                                    <button @click="expanded = !expanded" class="w-full text-left font-medium flex justify-between items-center">
                                        {{ $faq->question }}
                                        <span x-text="expanded ? '−' : '+'" class="text-gray-400"></span>
                                    </button>
                                    <div x-show="expanded" x-collapse class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $faq->answer }}</div>
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
@if (!$navSections->contains('specifications') && $specificationGroups->isEmpty())
    <section id="specifications" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16" aria-labelledby="specifications-heading">
        <h2 id="specifications-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Specifications</h2>
        <p class="text-sm text-gray-400 mt-5">No specifications listed yet.</p>
    </section>
@endif

@if ($relatedCards->isNotEmpty())
    <section id="related" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16" aria-labelledby="related-heading">
        <h2 id="related-heading" class="text-xl lg:text-2xl font-bold tracking-tight">You May Also Like</h2>
        <div class="grid {{ \App\Support\IndustryConfig::currentGet('ui.grid_class', 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4') }} gap-x-4 gap-y-8 sm:gap-x-6 mt-5">
            @foreach ($relatedCards as $card)
                <x-dynamic-component :component="\App\Support\IndustryConfig::currentGet('ui.card_component', 'storefront.product-cards.default')" :card="$card" />
            @endforeach
        </div>
    </section>
@endif
@if ($crossSellCards->isNotEmpty())
    <section id="cross-sells" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16" aria-labelledby="cross-sells-heading">
        <h2 id="cross-sells-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Complete Your Setup</h2>
        <div class="grid {{ \App\Support\IndustryConfig::currentGet('ui.grid_class', 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4') }} gap-x-4 gap-y-8 sm:gap-x-6 mt-5">
            @foreach ($crossSellCards as $card)
                <x-dynamic-component :component="\App\Support\IndustryConfig::currentGet('ui.card_component', 'storefront.product-cards.default')" :card="$card" />
            @endforeach
        </div>
    </section>
@endif
@if ($upsellCards->isNotEmpty())
    <section id="upsells" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16" aria-labelledby="upsells-heading">
        <h2 id="upsells-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Upgrade Your Choice</h2>
        <div class="grid {{ \App\Support\IndustryConfig::currentGet('ui.grid_class', 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4') }} gap-x-4 gap-y-8 sm:gap-x-6 mt-5">
            @foreach ($upsellCards as $card)
                <x-dynamic-component :component="\App\Support\IndustryConfig::currentGet('ui.card_component', 'storefront.product-cards.default')" :card="$card" />
            @endforeach
        </div>
    </section>
@endif
@if ($frequentlyBoughtCards->isNotEmpty())
    <section id="frequently-bought-together" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16" aria-labelledby="frequently-bought-heading">
        <h2 id="frequently-bought-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Frequently Bought Together</h2>
        <div class="grid {{ \App\Support\IndustryConfig::currentGet('ui.grid_class', 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4') }} gap-x-4 gap-y-8 sm:gap-x-6 mt-5">
            @foreach ($frequentlyBoughtCards as $card)
                <x-dynamic-component :component="\App\Support\IndustryConfig::currentGet('ui.card_component', 'storefront.product-cards.default')" :card="$card" />
            @endforeach
        </div>
    </section>
@endif
@if ($compatibleAccessoryCards->isNotEmpty())
    <section id="compatible-accessories" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16" aria-labelledby="compatible-accessories-heading">
        <h2 id="compatible-accessories-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Compatible Accessories</h2>
        <div class="grid {{ \App\Support\IndustryConfig::currentGet('ui.grid_class', 'grid-cols-2 md:grid-cols-3 lg:grid-cols-4') }} gap-x-4 gap-y-8 sm:gap-x-6 mt-5">
            @foreach ($compatibleAccessoryCards as $card)
                <x-dynamic-component :component="\App\Support\IndustryConfig::currentGet('ui.card_component', 'storefront.product-cards.default')" :card="$card" />
            @endforeach
        </div>
    </section>
@endif
@if ($recentlyViewedCards->isNotEmpty())
    <section id="recently-viewed" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10 lg:mt-16" aria-labelledby="recently-viewed-heading">
        <h2 id="recently-viewed-heading" class="text-xl lg:text-2xl font-bold tracking-tight">Recently Viewed</h2>
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
