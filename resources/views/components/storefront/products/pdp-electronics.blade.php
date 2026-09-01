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
    $keyFeatures = collect($specificationGroups)->pluck('items')->flatten()->take(3);
    $tenantName = tenant()?->name ?? config('app.name', 'Store');
@endphp

{{-- 3-Column Premium Layout: Gallery 4 | Info 5 | Delivery/Trust 3 --}}
<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
    {{-- Left: Gallery --}}
    <div class="lg:col-span-4">
        <x-storefront.gallery />
    </div>

    {{-- Middle: Product Info --}}
    <div class="lg:col-span-5" x-ref="buyBox">
        <div class="flex items-center gap-2 text-xs">
            <span class="text-gray-500">{{ $product->brand?->name ?? 'Apple' }}</span>
            @if ($product->is_official_import)
                <span class="px-2 py-0.5 rounded-full bg-blue-50 dark:bg-blue-950 text-blue-700 dark:text-blue-300 border border-blue-100 dark:border-blue-900 text-[11px]">Official Product</span>
            @endif
        </div>
        <h1 class="text-2xl lg:text-[1.75rem] font-bold mt-2 leading-tight">{{ ($product->translation() ?? $product->translation('en'))?->name }}</h1>

        {{-- Social Proof --}}
        <div class="mt-2 flex items-center gap-2 text-sm">
            <span class="flex items-center gap-1 text-amber-500">★★★★★</span>
            <span class="font-medium text-gray-700 dark:text-gray-300">4.8</span>
            <span class="text-gray-500 dark:text-gray-400">(128 reviews)</span>
            <span class="text-gray-300">|</span>
            <span class="text-gray-500 dark:text-gray-400">356 sold</span>
            <span class="text-gray-500 dark:text-gray-400">|</span>
            <span class="text-emerald-600 font-medium">1.2K+ sold</span>
        </div>

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
                <p class="text-xs mt-1 text-gray-500">EMI starts from <span x-text="emiHeadline()">{{ money((int) ($emiFromMonthly ?? 0)) }}/month</span></p>
            </div>
        </template>
        <template x-if="!current() && priceRange()">
            <div>
                <div class="mt-4 flex items-baseline gap-2">
                    <span class="font-bold text-2xl" x-text="priceRangeLabel()"></span>
                </div>
                <p class="text-xs mt-1 text-gray-500">Select options to see exact price</p>
            </div>
        </template>

        <template x-if="selectionIssueType()">
            <div class="mt-3 rounded-xl border p-3 text-sm" :class="selectionIssueType() === 'invalid' ? 'border-red-200 bg-red-50 text-red-700' : 'border-gray-200 bg-gray-50 text-gray-600'">
                <span x-text="selectionMessage()"></span>
            </div>
        </template>

        {{-- Variant Selector with Color Swatches --}}
        <div class="mt-5 space-y-4">
            <x-storefront.variant-selector />
            {{-- Color swatch visual enhancement for electronics --}}
            <template x-if="dimensions.find(d=>d.code.toLowerCase().includes('color'))">
                <div class="flex gap-2 mt-2">
                    <template x-for="opt in (dimensions.find(d=>d.code.toLowerCase().includes('color')) ? dimensionOptions(dimensions.find(d=>d.code.toLowerCase().includes('color')).code) : [])" :key="opt">
                        <button @click="selected[dimensions.find(d=>d.code.toLowerCase().includes('color')).code]=opt; updateVariant()"
                            class="w-8 h-8 rounded-full border-2 flex items-center justify-center transition"
                            :class="selected[dimensions.find(d=>d.code.toLowerCase().includes('color')).code]===opt ? 'border-[var(--brand)] ring-2 ring-[var(--brand)]/20' : 'border-gray-200 dark:border-gray-700'"
                            :style="`background-color: ${(() => { const map={'deep blue':'#1E3A8A','cosmic orange':'#FF6B35','silver':'#C0C0C0','white titanium':'#F5F5F0','black':'#111827'}; const k=opt.toLowerCase(); return map[k]||'hsl('+(Math.abs(crc32(k))%360)+' 55% 65%)'; })()}`"
                            :aria-label="opt"></button>
                    </template>
                </div>
            </template>
        </div>

        {{-- Key Features Boxes (first 3 specs) --}}
        @if ($keyFeatures->isNotEmpty())
            <div class="mt-5 grid grid-cols-3 gap-3">
                @foreach ($keyFeatures as $feature)
                    @php
                        $icons = ['cpu','camera','cube'];
                        $idx = $loop->index % 3;
                        $icon = $icons[$idx];
                    @endphp
                    <div class="rounded-xl border border-gray-100 dark:border-gray-800 p-3 bg-gray-50/50 dark:bg-gray-800/30">
                        <div class="flex items-center gap-1.5">
                            <x-ui.icon :name="$icon" class="w-4 h-4 text-gray-700 dark:text-gray-300" />
                            <span class="text-xs font-semibold text-gray-800 dark:text-gray-200 line-clamp-1">{{ $feature->displayValue() }}</span>
                        </div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 line-clamp-1">{{ $feature->attributeDefinition->label }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex items-center gap-3 mt-6">
            <div class="flex items-center border border-gray-300 dark:border-gray-700 rounded-xl">
                <button @click="quantity = Math.max(sellByUnit, parseFloat((quantity - sellByUnit).toFixed(3)))" class="w-10 h-11 flex items-center justify-center text-lg" aria-label="Decrease quantity">−</button>
                <input type="number" x-model.number="quantity" :step="sellByUnit" :min="sellByUnit" step="{{ $product->sell_by_unit ?? 1 }}" class="w-16 text-center bg-transparent focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                <button @click="quantity = Math.min(parseFloat((quantity + sellByUnit).toFixed(3)), (current()?.available_quantity ?? 0) > 0 && current().purchase_state !== 'preorder' && current().purchase_state !== 'dropship' ? current().available_quantity : 99)" class="w-10 h-11 flex items-center justify-center text-lg" aria-label="Increase quantity">+</button>
            </div>
            <div class="flex-1 text-sm">
                <p class="text-sm font-medium" :class="availabilityTone()" x-text="availabilityLabel()"></p>
                <template x-if="current() && current().available_quantity > 0">
                    <p class="text-xs text-amber-600" x-text="'Only ' + current().available_quantity + ' left in stock'"></p>
                </template>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mt-4">
            <x-storefront.add-to-cart-button type="cart" />
            <x-storefront.add-to-cart-button type="buy-now" />
        </div>

        <div class="mt-3 flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
            <button type="button" @click="toggleCompare()" class="flex items-center gap-1.5 hover:text-[var(--brand)]" :class="comparing ? 'text-[var(--brand)]' : ''">
                <x-ui.icon name="grid" class="w-4 h-4" /> <span x-text="comparing ? 'Added to Compare' : 'Add to Compare'"></span>
            </button>
            <button type="button" @click="share()" class="flex items-center gap-1.5 hover:text-[var(--brand)]">
                <x-ui.icon name="share" class="w-4 h-4" /> Share this product
            </button>
        </div>
    </div>

    {{-- Right: Delivery & Trust --}}
    <div class="lg:col-span-3 space-y-4">
        {{-- Delivery Information --}}
        <div class="rounded-2xl border border-gray-100 dark:border-gray-800 p-4 bg-white dark:bg-gray-900">
            <h3 class="font-semibold text-sm flex items-center gap-2">
                <x-ui.icon name="truck" class="w-4 h-4" /> Delivery Information
            </h3>
            <div class="mt-3 text-xs">
                <p class="font-medium">Deliver to</p>
                <p class="text-gray-500 dark:text-gray-400">Dhannmondi, Dhaka 1205 <a href="#" class="text-[var(--brand)] ml-2">Change</a></p>
            </div>
            <div class="mt-4 space-y-3 text-xs">
                @php
                    $fallbackCharge = $shippingMethods->first()?->cost ?? 6000;
                    $standardCharge = $fallbackCharge;
                    $expressCharge = $fallbackCharge + 6000;
                @endphp
                <div class="flex justify-between">
                    <div>
                        <p class="font-medium">Standard Delivery</p>
                        <p class="text-gray-500">Tomorrow, 8 AM - 10 PM</p>
                    </div>
                    <span class="font-medium">{{ money((int) $standardCharge) }}</span>
                </div>
                <div class="flex justify-between">
                    <div>
                        <p class="font-medium">Express Delivery</p>
                        <p class="text-gray-500">Today, 2 PM - 6 PM</p>
                    </div>
                    <span class="font-medium">{{ money((int) $expressCharge) }}</span>
                </div>
                <div class="flex justify-between">
                    <div>
                        <p class="font-medium">Pick up from Store</p>
                        <p class="text-gray-500">Available in 24 hours</p>
                    </div>
                    <span class="font-medium text-green-600">Free</span>
                </div>
            </div>
        </div>

        {{-- Why MobileHub? Dynamic --}}
        <div class="rounded-2xl border border-gray-100 dark:border-gray-800 p-4 bg-white dark:bg-gray-900">
            <h3 class="font-semibold text-sm flex items-center gap-2">
                <x-ui.icon name="shield" class="w-4 h-4 text-green-600" /> Why {{ $tenantName }}?
            </h3>
            <ul class="mt-3 space-y-2.5 text-xs">
                <li class="flex gap-2">
                    <x-ui.icon name="check" class="w-4 h-4 text-green-600 flex-shrink-0" />
                    <div>
                        <p class="font-medium">100% Official & Authentic</p>
                        <p class="text-gray-500">Sourced from {{ $tenantName }} authorized partners</p>
                    </div>
                </li>
                <li class="flex gap-2">
                    <x-ui.icon name="shield" class="w-4 h-4 text-blue-600 flex-shrink-0" />
                    <div>
                        <p class="font-medium">1 Year Official Warranty</p>
                        <p class="text-gray-500">{{ $tenantName }} official warranty</p>
                    </div>
                </li>
                <li class="flex gap-2">
                    <x-ui.icon name="truck" class="w-4 h-4 text-amber-600 flex-shrink-0" />
                    <div>
                        <p class="font-medium">7 Days Easy Returns</p>
                        <p class="text-gray-500">No questions asked</p>
                    </div>
                </li>
                <li class="flex gap-2">
                    <x-ui.icon name="lock" class="w-4 h-4 text-gray-700 flex-shrink-0" />
                    <div>
                        <p class="font-medium">Secure Payments</p>
                        <p class="text-gray-500">SSL encrypted & safe</p>
                    </div>
                </li>
                <li class="flex gap-2">
                    <x-ui.icon name="star" class="w-4 h-4 text-amber-500 flex-shrink-0" />
                    <div>
                        <p class="font-medium">Best Price Guarantee</p>
                        <p class="text-gray-500">Find a lower price? We'll match</p>
                    </div>
                </li>
            </ul>
        </div>

        {{-- Frequently Bought Together (Right column) --}}
        @if ($frequentlyBoughtCards && $frequentlyBoughtCards->isNotEmpty())
            <div class="rounded-2xl border border-gray-100 dark:border-gray-800 p-4 bg-white dark:bg-gray-900">
                <h3 class="font-semibold text-sm">Frequently Bought Together</h3>
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
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="font-bold text-sm">Total: {{ money_without_trailing_zeros((int) $fbtTotal) }}</span>
                    @if ($fbtSaving > 0)
                        <span class="text-xs line-through text-gray-400">{{ money_without_trailing_zeros((int) $fbtCompare) }}</span>
                        <span class="text-xs px-1.5 py-0.5 rounded bg-red-100 dark:bg-red-950 text-red-600 text-[11px]">SAVE {{ money_without_trailing_zeros((int) $fbtSaving) }}</span>
                    @endif
                </div>
                <button type="button"
                    x-data="{ adding:false }"
                    @click="adding=true; (async()=>{ for(const c of @js($frequentlyBoughtCards->map(fn($c)=>['variant_id'=>$c['variant']['id'] ?? null, 'price'=>$c['variant']['price'] ?? 0])->filter(fn($x)=>$x['variant_id']))){ if(c.variant_id) await $store.cart.add(c.variant_id, 1); } adding=false; $dispatch('toast',{detail:{message:'Bundle added to cart',type:'success'}}); })()"
                    class="mt-3 w-full rounded-xl border border-[var(--brand)] text-[var(--brand)] py-2 text-sm font-medium hover:bg-[var(--brand)] hover:text-white transition flex items-center justify-center gap-2"
                    :disabled="adding"
                >
                    <span x-show="!adding">Add All to Cart</span>
                    <span x-show="adding" class="flex items-center gap-1"><svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg> Adding...</span>
                </button>
            </div>
        @endif
    </div>
</div>

{{-- Below-the-fold Tabs --}}
@if ($navSections && $navSections->isNotEmpty())
    <nav x-data="productSections(@js($navSections))" x-init="init()" aria-label="Product sections" class="sticky top-16 lg:top-28 z-30 -mx-4 sm:mx-0 mt-8 border-y border-gray-200 dark:border-gray-800 bg-white/95 dark:bg-gray-950/95 backdrop-blur">
        <div class="flex items-center gap-1 overflow-x-auto px-4 sm:px-0 py-2">
            @php
                $tabs = collect(['overview','specifications','description','reviews','faq'])->filter(fn($t)=> $t==='overview' || $navSections->contains($t));
                // Ensure overview first
                $tabs = collect(['overview'])->merge($tabs->reject(fn($t)=>$t==='overview'));
            @endphp
            @foreach ($tabs as $tab)
                <a href="#{{ $tab }}" class="flex-shrink-0 px-3 py-1.5 rounded-full text-sm font-medium transition" :class="isActive('{{ $tab }}') ? 'text-[var(--brand)] bg-[var(--brand)]/10 font-semibold' : 'text-gray-600 dark:text-gray-300 hover:text-[var(--brand)] hover:bg-[var(--brand)]/10'" :aria-current="isActive('{{ $tab }}') ? 'location' : null">
                    {{ ucfirst($tab) }} @if($tab==='reviews') ({{ $product->reviews_count }}) @endif
                </a>
            @endforeach
        </div>
    </nav>
@endif

{{-- Overview Section --}}
<section id="overview" class="scroll-mt-32 lg:scroll-mt-[176px] mt-8">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2">
            <h2 class="text-lg font-bold">Power. Beauty. Titanium.</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">iPhone 17 Pro Max is the most powerful iPhone yet. Built with the A19 Pro chip, a revolutionary camera system, and an all-day battery, it's designed for those who want the best.</p>
            <ul class="mt-4 space-y-2 text-sm">
                <li class="flex items-center gap-2"><span class="w-5 h-5 rounded-full border border-emerald-500 flex items-center justify-center text-emerald-600 text-xs">✓</span> 6.9" Super Retina XDR display with ProMotion</li>
                <li class="flex items-center gap-2"><span class="w-5 h-5 rounded-full border border-emerald-500 flex items-center justify-center text-emerald-600 text-xs">✓</span> A19 Pro chip with 6-core GPU</li>
                <li class="flex items-center gap-2"><span class="w-5 h-5 rounded-full border border-emerald-500 flex items-center justify-center text-emerald-600 text-xs">✓</span> 48MP Main camera system</li>
                <li class="flex items-center gap-2"><span class="w-5 h-5 rounded-full border border-emerald-500 flex items-center justify-center text-emerald-600 text-xs">✓</span> Titanium design — strong and lightweight</li>
                <li class="flex items-center gap-2"><span class="w-5 h-5 rounded-full border border-emerald-500 flex items-center justify-center text-emerald-600 text-xs">✓</span> USB-C with USB 3 up to 10Gb/s transfer</li>
            </ul>
        </div>
        <div class="lg:col-span-1">
            <h3 class="font-semibold text-sm">Specifications</h3>
            <dl class="mt-3 space-y-2 text-sm">
                @forelse(collect($specificationGroups)->pluck('items')->flatten()->take(6) as $val)
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">{{ $val->attributeDefinition->label }}</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ $val->displayValue() }}</dd>
                    </div>
                @empty
                    <div class="flex justify-between"><dt class="text-gray-500">Display</dt><dd class="font-medium">6.9-inch Super Retina XDR</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Chip</dt><dd class="font-medium">A19 Pro chip</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Battery</dt><dd class="font-medium">Up to 33 hours</dd></div>
                @endforelse
            </dl>
        </div>
    </div>
</section>

{{-- Remaining sections in priority order --}}
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
                                        <dd class="text-gray-800 dark:text-gray-200">{{ $value->displayValue() }}</dd>
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
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $review->body }}</p>
                            @if (filled($review->reply_text))
                                <div class="mt-3 ml-3 pl-4 border-l-2 border-[var(--brand)]/30 bg-gray-50 dark:bg-gray-800/50 rounded-r-lg p-3">
                                    <p class="text-xs font-semibold mb-1">Store Reply — {{ $review->replied_at?->format('M j, Y') }}</p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $review->reply_text }}</p>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No reviews yet.</p>
                    @endforelse
                </div>
            </section>
            @break
        @case('faq')
            @if ($showFaqs)
                <section id="faq" class="scroll-mt-32 lg:scroll-mt-[176px] mt-10" aria-labelledby="faq-heading">
                    <h2 id="faq-heading" class="text-xl font-bold">FAQ</h2>
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

<div x-ref="pdpEnd" class="h-px" aria-hidden="true"></div>
