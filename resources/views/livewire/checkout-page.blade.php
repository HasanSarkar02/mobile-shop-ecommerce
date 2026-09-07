{{-- resources/views/livewire/checkout-page.blade.php — Enterprise checkout, BN-ready, mobile-perfect --}}
@php
    $stepActive = 2; // Cart(1) -> Checkout(2) -> Confirmation(3)
@endphp
<div class="bg-[#F8FAFC] dark:bg-gray-950 min-h-screen">
    {{-- Top trust ribbon — distinctive signature for BD commerce --}}
    <div class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800">
        <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 py-3">
                <div class="flex items-center gap-2 text-xs font-medium text-gray-600 dark:text-gray-400">
                    <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> {{ __('SSL secured') }}</span>
                    <span class="w-px h-3 bg-gray-200 dark:bg-gray-700 hidden sm:block"></span>
                    <span class="hidden sm:inline-flex items-center gap-1.5"><x-ui.icon name="truck" class="w-3.5 h-3.5" /> {{ __('Cash on Delivery available') }}</span>
                    <span class="w-px h-3 bg-gray-200 dark:bg-gray-700 hidden sm:block"></span>
                    <span class="hidden sm:inline-flex items-center gap-1.5"><x-ui.icon name="refresh" class="w-3.5 h-3.5" /> {{ __('7-day return') }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <span class="hidden sm:inline">{{ __('Need help?') }}</span>
                    @if(tenant()->contact_phone)
                        <a href="tel:{{ tenant()->contact_phone }}" class="font-semibold text-[var(--brand)] hover:underline">{{ __('Call us') }}: {{ tenant()->contact_phone }}</a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-[1280px] mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 pb-[calc(9rem+env(safe-area-inset-bottom))] lg:pb-8">
        {{-- Header + Stepper --}}
        <div class="mb-6">
            <nav class="text-xs text-gray-500 mb-2">
                <a href="{{ route('storefront.home') }}" class="hover:text-[var(--brand)]">{{ __('Home') }}</a>
                <span class="mx-1">/</span>
                <span class="text-gray-900 dark:text-gray-100 font-medium">{{ __('Checkout') }}</span>
            </nav>
            <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
                <div>
                    <h1 class="text-[22px] sm:text-2xl font-bold tracking-tight text-gray-900 dark:text-white" style="font-family: 'Instrument Sans', 'Hind Siliguri', sans-serif;">{{ __('Secure checkout') }}</h1>
                    <p class="text-sm text-gray-500 mt-1">{{ __('Your order is protected') }} — {{ __('SSL secured') }} · {{ __('7-day return') }}</p>
                </div>
                {{-- Stepper — structure is information: sequence matters --}}
                <div class="flex items-center gap-2 sm:gap-3">
                    @php $steps = [1=>__('Cart'), 2=>__('Checkout'), 3=>__('Confirmation')]; @endphp
                    @foreach($steps as $i=>$label)
                        <div class="flex items-center gap-2 sm:gap-3">
                            <div class="flex items-center gap-2">
                                <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full flex items-center justify-center text-xs font-bold border transition
                                    {{ $i < $stepActive ? 'bg-emerald-500 border-emerald-500 text-white' : ($i==$stepActive ? 'bg-[var(--brand)] border-[var(--brand)] text-white shadow-soft' : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700 text-gray-400') }}">
                                    @if($i < $stepActive)
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path d="M5 13l4 4L19 7"/></svg>
                                    @else
                                        {{ $i }}
                                    @endif
                                </span>
                                <span class="hidden sm:inline text-sm {{ $i==$stepActive ? 'font-semibold text-gray-900 dark:text-white' : 'text-gray-500' }}">{{ $label }}</span>
                                <span class="sm:hidden text-xs {{ $i==$stepActive ? 'font-semibold text-gray-900 dark:text-white' : 'text-gray-500' }}">{{ $label }}</span>
                            </div>
                            @if($i < 3)
                                <span class="w-6 sm:w-10 h-px {{ $i < $stepActive ? 'bg-emerald-500' : 'bg-gray-200 dark:bg-gray-800' }}"></span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($issues)
            <div class="mb-6 rounded-2xl border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/30 p-4 flex gap-3">
                <span class="w-8 h-8 rounded-full bg-amber-500 text-white flex items-center justify-center flex-shrink-0">!</span>
                <div class="space-y-1">
                    @foreach ($issues as $issue)
                        <p class="text-sm font-medium text-amber-900 dark:text-amber-200">{{ $issue }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-start">
            {{-- Left: Forms --}}
            <form wire:submit="placeOrder" class="lg:col-span-8 space-y-5" x-data @submit="$nextTick(() => { const el = document.querySelector('.border-red-300, .bg-red-50'); if(el) el.scrollIntoView({behavior:'smooth', block:'center'}); })">
                {{-- 01 Delivery Address --}}
                <section class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-soft overflow-hidden">
                    <div class="px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-full bg-[var(--brand)] text-white text-sm font-bold flex items-center justify-center">01</span>
                            <div>
                                <h2 class="text-sm font-bold tracking-tight">{{ __('Delivery Address') }}</h2>
                                <p class="text-xs text-gray-500 hidden sm:block">{{ __('Exact delivery charge calculated at checkout based on your address.') }}</p>
                            </div>
                        </div>
                        <span class="hidden sm:inline-flex items-center gap-1 text-xs text-emerald-700 bg-emerald-50 dark:bg-emerald-950/30 px-2.5 py-1 rounded-full border border-emerald-100 dark:border-emerald-900"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> {{ __('SSL secured') }}</span>
                    </div>

                    <div class="p-5 sm:p-6">
                        @if ($customer)
                            <div class="space-y-3">
                                @forelse ($addresses as $address)
                                    <label class="group flex items-start gap-3 p-4 rounded-2xl border-2 cursor-pointer transition text-left
                                        {{ $selectedAddressId === $address->id ? 'border-[var(--brand)] bg-[var(--brand)]/[0.04] shadow-soft' : 'border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700 bg-white dark:bg-gray-900' }}">
                                        <input type="radio" wire:model.live="selectedAddressId" value="{{ $address->id }}" class="mt-1 w-4 h-4 text-[var(--brand)] focus:ring-[var(--brand)] border-gray-300">
                                        <span class="flex-1 min-w-0">
                                            <span class="flex items-center gap-2">
                                                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $address->recipient_name }}</span>
                                                @if($selectedAddressId === $address->id)<span class="text-[11px] font-bold tracking-wide uppercase bg-[var(--brand)] text-white px-1.5 py-0.5 rounded">Selected</span>@endif
                                            </span>
                                            <span class="block text-sm text-gray-600 dark:text-gray-300 mt-0.5 leading-relaxed">
                                                {{ $address->address_line_1 }}@if($address->area), {{ $address->area }}@endif · {{ $address->city }} · {{ $address->phone }}
                                            </span>
                                            @if($address->bd_division_id)
                                                <span class="inline-flex mt-1 text-xs text-gray-500 bg-gray-50 dark:bg-gray-800 px-2 py-1 rounded-full">{{ $address->bd_division_name ?? '' }} @if($address->bd_district_name) → {{ $address->bd_district_name }} @endif</span>
                                            @endif
                                        </span>
                                        <span class="w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0 mt-0.5 {{ $selectedAddressId === $address->id ? 'border-[var(--brand)] bg-[var(--brand)]' : 'border-gray-300' }}">
                                            @if($selectedAddressId === $address->id)<svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path d="M5 13l4 4L19 7"/></svg>@endif
                                        </span>
                                    </label>
                                @empty
                                    <div class="rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-dashed border-gray-200 dark:border-gray-700 p-4 text-sm text-gray-600 dark:text-gray-300">
                                        {{ __('No saved addresses yet.') }} <a href="{{ route('storefront.account.addresses') }}" class="font-semibold text-[var(--brand)] hover:underline">{{ __('Add one') }}</a>
                                    </div>
                                @endforelse
                                <a href="{{ route('storefront.account.addresses') }}" class="inline-flex text-sm font-medium text-[var(--brand)] hover:underline">+ {{ __('Add one') }}</a>
                                @error('selectedAddressId')
                                    <p class="text-xs font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900 rounded-xl px-3 py-2 flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-red-600"></span>{{ $message }}</p>
                                @enderror
                            </div>
                        @else
                            {{-- Guest: Contact + Address with BD geo ribbon --}}
                            <div class="space-y-5">
                                <div>
                                    <p class="text-xs font-semibold tracking-widest uppercase text-gray-500 mb-3">{{ __('Contact Information') }}</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <x-ui.input name="guestName" wire:model="guestName" label="{{ __('Full name') }} *" placeholder="e.g. Rahim Uddin" />
                                        <x-ui.input name="guestPhone" wire:model="guestPhone" label="{{ __('Phone') }} *" placeholder="01XXXXXXXXX" />
                                        <x-ui.input name="guestEmail" wire:model="guestEmail" label="{{ __('Email') }}" placeholder="example@email.com ({{ __('Optional') }})" class="sm:col-span-2" />
                                    </div>
                                </div>

                                <div class="h-px bg-gray-100 dark:bg-gray-800"></div>

                                <div>
                                    <p class="text-xs font-semibold tracking-widest uppercase text-gray-500 mb-3">{{ __('Delivery Address') }}</p>
                                    {{-- Bangladesh geo ribbon — signature element --}}
                                    <div class="mb-4 rounded-xl bg-gradient-to-r from-[var(--brand)]/[0.06] to-transparent border border-[var(--brand)]/10 p-3 flex flex-wrap items-center gap-2 text-xs">
                                        <span class="font-semibold text-gray-900 dark:text-white">BD</span>
                                        <span class="w-1 h-1 rounded-full bg-gray-400"></span>
                                        <span class="{{ $bd_division_id ? 'text-gray-900 dark:text-white font-medium' : 'text-gray-500' }}">{{ $divisions->firstWhere('id',$bd_division_id)?->name_en ?? __('Division') }}</span>
                                        <span class="text-gray-400">→</span>
                                        <span class="{{ $bd_district_id ? 'text-gray-900 dark:text-white font-medium' : 'text-gray-500' }}">{{ $districts->firstWhere('id',$bd_district_id)?->name_en ?? __('District') }}</span>
                                        <span class="text-gray-400">→</span>
                                        <span class="{{ $bd_upazila_id ? 'text-gray-900 dark:text-white font-medium' : 'text-gray-500' }}">{{ $upazilas->firstWhere('id',$bd_upazila_id)?->name_en ?? __('Upazila') }}</span>
                                        <span class="ml-auto hidden sm:inline text-[11px] text-gray-500">{{ __('Exact delivery charge calculated at checkout based on your address.') }}</span>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <x-ui.input name="guestAddress.recipient_name" wire:model="guestAddress.recipient_name" label="{{ __('Recipient name') }} *" placeholder="e.g. Karim Ahmed" />
                                        <x-ui.input name="guestAddress.phone" wire:model="guestAddress.phone" label="{{ __('Delivery phone') }} *" placeholder="01XXXXXXXXX" />
                                        <x-ui.input name="guestAddress.address_line_1" wire:model="guestAddress.address_line_1" label="{{ __('Address') }} *" class="sm:col-span-2" placeholder="{{ __('House, Road, Area, Thana') }}" />
                                        <div>
                                            <label for="bd_division_id" class="block text-[12px] font-bold tracking-wide uppercase text-gray-500 dark:text-gray-400 mb-1.5 ml-1">{{ __('Division') }} *</label>
                                            <select id="bd_division_id" wire:model.live="bd_division_id" class="w-full h-11 px-3.5 rounded-xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-sm shadow-sm focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 outline-none transition">
                                                <option value="">{{ __('Select Division') }}</option>
                                                @foreach ($divisions as $division)
                                                    <option value="{{ $division->id }}">{{ $division->name_en }} — {{ $division->name_bn }}</option>
                                                @endforeach
                                            </select>
                                            @error('bd_division_id') <p class="mt-1.5 ml-1 text-xs font-medium text-red-600 dark:text-red-400 flex items-center gap-1"><span class="w-1 h-1 rounded-full bg-red-600"></span>{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="bd_district_id" class="block text-[12px] font-bold tracking-wide uppercase text-gray-500 dark:text-gray-400 mb-1.5 ml-1">{{ __('District') }} *</label>
                                            <select id="bd_district_id" wire:model.live="bd_district_id" class="w-full h-11 px-3.5 rounded-xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-sm shadow-sm focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 outline-none transition">
                                                <option value="">{{ __('Select District') }}</option>
                                                @foreach ($districts as $district)
                                                    <option value="{{ $district->id }}">{{ $district->name_en }} — {{ $district->name_bn }}</option>
                                                @endforeach
                                            </select>
                                            @error('bd_district_id') <p class="mt-1.5 ml-1 text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label for="bd_upazila_id" class="block text-[12px] font-bold tracking-wide uppercase text-gray-500 dark:text-gray-400 mb-1.5 ml-1">{{ __('Upazila') }}</label>
                                            <select id="bd_upazila_id" wire:model.live="bd_upazila_id" class="w-full h-11 px-3.5 rounded-xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-sm shadow-sm focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 outline-none transition">
                                                <option value="">{{ __('Select Upazila') }}</option>
                                                @foreach ($upazilas as $upazila)
                                                    <option value="{{ $upazila->id }}">{{ $upazila->name_en }} — {{ $upazila->name_bn }}</option>
                                                @endforeach
                                            </select>
                                            @error('bd_upazila_id') <p class="mt-1.5 ml-1 text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                        </div>
                                        <x-ui.input name="guestAddress.city" wire:model="guestAddress.city" label="{{ __('City (legacy)') }}" placeholder="e.g. Dhaka" />
                                    </div>
                                </div>
                                <p class="text-sm text-gray-500">{{ __('Already have an account?') }} <a href="{{ route('storefront.login') }}" class="font-semibold text-[var(--brand)] hover:underline">{{ __('Log in') }}</a></p>
                            </div>
                        @endif
                    </div>
                </section>

                {{-- 02 Shipping Method --}}
                <section class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-soft overflow-hidden">
                    <div class="px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
                        <span class="w-8 h-8 rounded-full bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-sm font-bold flex items-center justify-center">02</span>
                        <h2 class="text-sm font-bold tracking-tight">{{ __('Shipping Method') }}</h2>
                    </div>
                    <div class="p-5 sm:p-6">
                        @if($shippingMethods->isEmpty())
                            <p class="text-sm text-gray-500">{{ __('Delivery charges calculated at checkout.') }}</p>
                        @else
                            <div class="grid gap-3">
                                @foreach ($shippingMethods as $method)
                                    @php $isSelected = $shippingMethodId === $method->id; @endphp
                                    <label class="group relative flex items-center justify-between gap-3 p-4 rounded-2xl border-2 cursor-pointer transition
                                        {{ $isSelected ? 'border-[var(--brand)] bg-[var(--brand)]/[0.05] shadow-soft' : 'border-gray-200 dark:border-gray-800 hover:border-gray-300 bg-white dark:bg-gray-900' }}">
                                        <span class="flex items-center gap-3 min-w-0">
                                            <input type="radio" wire:model.live="shippingMethodId" value="{{ $method->id }}" class="w-4 h-4 text-[var(--brand)] focus:ring-[var(--brand)] border-gray-300">
                                            <span class="min-w-0">
                                                <span class="text-sm font-semibold text-gray-900 dark:text-white block leading-none">{{ $method->name }}</span>
                                                <span class="text-xs text-gray-500">2–4 {{ __('available') }}</span>
                                            </span>
                                        </span>
                                        <span class="flex items-center gap-2 flex-shrink-0">
                                            @if ($method->type === \App\Enums\ShippingMethodType::Free)
                                                <span class="text-xs font-bold tracking-wide uppercase px-2.5 py-1 rounded-full bg-emerald-500 text-white">{{ __('Free') }}</span>
                                            @elseif($method->type === \App\Enums\ShippingMethodType::Pickup)
                                                <span class="text-xs font-bold tracking-wide uppercase px-2 py-1 rounded-full bg-blue-600 text-white">{{ __('Pickup') }}</span>
                                            @else
                                                <span class="text-sm font-bold">{{ money((int)$method->cost) }}</span>
                                            @endif
                                            @if($isSelected)<span class="w-6 h-6 rounded-full bg-[var(--brand)] text-white flex items-center justify-center"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path d="M5 13l4 4L19 7"/></svg></span>@endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('shippingMethodId')
                                <p class="mt-3 text-xs font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900 rounded-xl px-3 py-2 flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-red-600"></span>{{ $message }}</p>
                            @enderror
                        @endif
                    </div>
                </section>

                {{-- 03 Payment Method --}}
                <section class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-soft overflow-hidden">
                    <div class="px-5 sm:px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
                        <span class="w-8 h-8 rounded-full bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-sm font-bold flex items-center justify-center">03</span>
                        <h2 class="text-sm font-bold tracking-tight">{{ __('Payment Method') }}</h2>
                        <span class="ml-auto hidden sm:inline-flex items-center gap-1.5 text-xs text-gray-500"><x-ui.icon name="lock" class="w-3.5 h-3.5" /> {{ __('SSL secured') }}</span>
                    </div>
                    <div class="p-5 sm:p-6">
                        <div class="grid gap-3">
                            @foreach ($paymentMethods as $method)
                                @php
                                    $isManualMfs = $method->type === \App\Enums\PaymentMethodType::ManualMfs;
                                    $isBank = $method->type === \App\Enums\PaymentMethodType::BankTransfer;
                                    $isCod = $method->type === \App\Enums\PaymentMethodType::Cod;
                                    $isManual = $isManualMfs || $isBank;
                                    $isSelected = $paymentMethodId === $method->id;
                                @endphp
                                <label class="group flex flex-col gap-0 p-4 rounded-2xl border-2 cursor-pointer transition
                                    {{ $isSelected ? 'border-[var(--brand)] bg-[var(--brand)]/[0.04] shadow-soft' : 'border-gray-200 dark:border-gray-800 hover:border-gray-300 bg-white dark:bg-gray-900' }}">
                                    <span class="flex items-center gap-3">
                                        <input type="radio" wire:model.live="paymentMethodId" value="{{ $method->id }}" class="w-4 h-4 text-[var(--brand)] focus:ring-[var(--brand)] border-gray-300">
                                        <span class="flex-1 min-w-0">
                                            <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $method->displayName() }}</span>
                                            <span class="block text-xs text-gray-500">
                                                @if($isCod) {{ __('Cash on Delivery') }} @elseif($isManual) {{ __('Manual verification') }} @endif
                                            </span>
                                        </span>
                                        @if ($isCod)
                                            <span class="hidden sm:inline text-xs font-bold tracking-wide uppercase px-2.5 py-1 rounded-full bg-emerald-500 text-white">COD</span>
                                        @elseif($isManual)
                                            <span class="hidden sm:inline text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">{{ __('Manual verification') }}</span>
                                        @endif
                                        <span class="w-6 h-6 rounded-full border-2 flex items-center justify-center flex-shrink-0 {{ $isSelected ? 'border-[var(--brand)] bg-[var(--brand)]' : 'border-gray-300' }}">
                                            @if($isSelected)<svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path d="M5 13l4 4L19 7"/></svg>@endif
                                        </span>
                                    </span>
                                    @if ($isSelected && ($isManual || $isCod))
                                        <div class="mt-3 ml-7 rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-700 p-3 text-sm">
                                            @if ($isManual)
                                                @if ($method->account_number)
                                                    <p class="flex flex-wrap items-center gap-2"><span class="text-gray-500 text-xs uppercase tracking-wide font-semibold">{{ __('Account:') }}</span> <span class="font-mono font-bold text-gray-900 dark:text-white">{{ $method->account_number }}</span> @if ($method->account_name) <span class="text-gray-500">— {{ $method->account_name }}</span> @endif <button type="button" onclick="navigator.clipboard.writeText('{{ $method->account_number }}')" class="ml-auto text-xs font-semibold text-[var(--brand)] hover:underline">Copy</button></p>
                                                @endif
                                                @if ($isBank && $method->bank_name)
                                                    <p class="mt-1"><span class="text-gray-500 text-xs uppercase tracking-wide font-semibold">{{ __('Bank:') }}</span> <span class="font-medium text-gray-900 dark:text-white">{{ $method->bank_name }} @if ($method->branch_name) ({{ $method->branch_name }}) @endif</span></p>
                                                @endif
                                                @if ($method->instructions)
                                                    <p class="mt-2 text-sm leading-relaxed text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $method->instructions }}</p>
                                                @endif
                                                <p class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/30 border border-amber-100 dark:border-amber-900 rounded-lg px-2.5 py-2">{{ __('After paying, submit your Transaction ID on the order confirmation page for verification.') }}</p>
                                            @else
                                                @if ($method->instructions)
                                                    <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $method->instructions }}</p>
                                                @else
                                                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('Pay with cash when your order is delivered.') }}</p>
                                                @endif
                                            @endif
                                        </div>
                                    @endif
                                </label>
                            @endforeach
                            @if ($paymentMethods->isEmpty())
                                <p class="text-sm text-gray-500 rounded-xl border border-dashed border-gray-200 dark:border-gray-700 p-4">{{ __('No payment methods are currently enabled for this store.') }}</p>
                            @endif
                            @error('paymentMethodId')
                                <p class="mt-3 text-xs font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900 rounded-xl px-3 py-2 flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-red-600"></span>{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </section>

                @if ($hasPreorder ?? false)
                    <section class="rounded-2xl border border-violet-200 dark:border-violet-900 bg-gradient-to-br from-violet-50 to-white dark:from-violet-950/30 dark:to-gray-900 p-5 shadow-soft">
                        <h3 class="text-sm font-bold text-violet-900 dark:text-violet-100 flex items-center gap-2">
                            <span class="w-1.5 h-6 rounded-full bg-violet-500"></span> {{ __('Pre-order in your cart') }}
                        </h3>
                        @if ($preorderEta)
                            <p class="mt-2 text-sm text-violet-800 dark:text-violet-200">{{ __('Earliest expected availability:') }} <strong class="font-mono">{{ $preorderEta->format('M j, Y') }}</strong></p>
                        @endif
                        <p class="mt-1 text-sm text-violet-700 dark:text-violet-300 leading-relaxed">
                            @if ($isMixed ?? false)
                                {{ __('In-stock items will ship now. Pre-order items will ship separately around their ETA.') }}
                            @else
                                {{ __('This order contains pre-order items that will ship around the ETA.') }}
                            @endif
                        </p>
                        <label class="mt-3 flex items-start gap-3 cursor-pointer p-3 rounded-xl bg-white dark:bg-gray-900 border border-violet-100 dark:border-violet-900">
                            <input type="checkbox" wire:model.live="preorder_ack" class="mt-0.5 w-4 h-4 rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                            <span class="text-sm leading-snug text-gray-700 dark:text-gray-200">{{ __('I understand that pre-order items are paid now and ship around the estimated availability date.') }}</span>
                        </label>
                        @error('preorder_ack') <p class="text-sm text-red-600 mt-2">{{ $message }}</p> @enderror
                    </section>
                @endif

                <section class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-soft p-5 sm:p-6">
                    <label for="customerNote" class="block text-[12px] font-bold tracking-widest uppercase text-gray-500 dark:text-gray-400 mb-1.5 ml-1">{{ __('Order Note') }} <span class="font-normal normal-case tracking-normal text-gray-400">({{ __('Order Note (optional)') }})</span></label>
                    <textarea id="customerNote" wire:model="customerNote" rows="2" placeholder="{{ __('Order Note') }}"
                        class="w-full min-h-[88px] rounded-xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-sm placeholder:text-gray-400 shadow-sm focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 outline-none transition p-3.5"></textarea>
                </section>

                <x-ui.button type="submit" variant="primary" size="lg" class="w-full hidden lg:inline-flex !py-3.5 text-base font-bold tracking-tight shadow-soft"
                    loading-target="placeOrder">
                    <span class="inline-flex items-center gap-2">{{ __('Place Order') }} <span class="hidden sm:inline">— {{ money((int) ($subtotal - $discount + $shippingCost)) }}</span></span>
                </x-ui.button>
                <p class="hidden lg:flex items-center justify-center gap-2 text-xs text-gray-500 mt-2"><x-ui.icon name="lock" class="w-3.5 h-3.5" /> {{ __('Your order is protected') }} · {{ __('SSL secured') }}</p>
            </form>

            {{-- Summary — sticky, collapsible on mobile --}}
            <aside class="lg:col-span-4 w-full lg:sticky lg:top-20 self-start">
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-soft overflow-hidden" x-data="{ open: window.innerWidth >= 1024 }" @resize.window="if(window.innerWidth>=1024) open=true">
                    <button type="button" @click="open=!open" class="w-full flex items-center justify-between gap-3 px-5 sm:px-6 py-4 lg:cursor-default">
                        <h2 class="text-sm font-bold tracking-tight flex items-center gap-2">
                            {{ __('Order summary') }}
                            <span class="text-xs font-medium text-gray-500 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">{{ count($cartItems) }} {{ __('Items') }}</span>
                        </h2>
                        <span class="lg:hidden inline-flex items-center gap-1 text-sm font-semibold text-[var(--brand)]">
                            <span x-text="open ? '{{ __('Hide order summary') }}' : '{{ __('Show order summary') }}'"></span>
                            <svg class="w-4 h-4 transition-transform" :class="open?'rotate-180':''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </button>

                    <div x-show="open" x-cloak x-transition class="px-5 sm:px-6 pb-5 sm:pb-6">
                        {{-- Coupon --}}
                        <div class="mb-4">
                            @if (!empty($appliedCoupon))
                                @if (!empty($isAutomaticCoupon))
                                    <div class="flex items-center gap-2 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900 text-sm text-emerald-800 dark:text-emerald-200">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> {{ __('Automatic free delivery applied') }}
                                    </div>
                                @else
                                    <div class="flex items-center justify-between p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900 text-sm">
                                        <span class="text-emerald-800 dark:text-emerald-200">{{ __('Coupon') }} <strong>{{ $appliedCoupon->code }}</strong> {{ __('Applied') }}</span>
                                        <button wire:click="removeCoupon" class="text-xs font-bold text-red-600 hover:underline">{{ __('Remove') }}</button>
                                    </div>
                                @endif
                            @else
                                <form wire:submit="applyCoupon" class="flex gap-2">
                                    <div class="flex-1 relative">
                                        <input wire:model="couponCode" placeholder="{{ __('Coupon code') }}" class="w-full h-11 rounded-xl border-gray-200 dark:border-gray-800 dark:bg-gray-950 text-sm placeholder:text-gray-400 focus:border-[var(--brand)] focus:ring-[var(--brand)] pr-10">
                                        @error('couponCode') <span class="absolute -bottom-5 left-0 text-xs text-red-600">{{ $message }}</span> @enderror
                                    </div>
                                    <x-ui.button type="submit" variant="secondary" class="!h-11 !px-5 font-semibold" loading-target="applyCoupon">{{ __('Apply') }}</x-ui.button>
                                </form>
                                @if (!empty($couponError))
                                    <p class="text-xs text-red-600 mt-2 bg-red-50 dark:bg-red-950/20 border border-red-100 dark:border-red-900 rounded-lg px-2.5 py-2">{{ $couponError }}</p>
                                @endif
                            @endif
                            @if (!empty($couponWarning))
                                <p class="text-xs leading-relaxed text-amber-800 dark:text-amber-200 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900 rounded-xl p-3 mt-3">{{ $couponWarning }}</p>
                            @endif
                            @if ($isMixed ?? false)
                                <div class="mt-3 rounded-xl bg-violet-50 dark:bg-violet-950/20 border border-violet-200 dark:border-violet-900 p-3 text-xs font-medium text-violet-800 dark:text-violet-200 text-center">
                                    {{ __('Stock ships now · Pre-order ships around ETA') }}
                                </div>
                            @endif
                        </div>

                        <div class="space-y-3 max-h-[42vh] sm:max-h-64 overflow-y-auto pr-1 -mr-1 custom-scrollbar">
                            @foreach ($cartItems as $item)
                                @php $isPre = $item->variant?->fulfillment_strategy?->value === 'preorder'; @endphp
                                <div class="flex gap-3">
                                    <div class="w-14 h-14 rounded-xl overflow-hidden bg-gray-50 dark:bg-gray-800 flex-shrink-0 relative border border-gray-100 dark:border-gray-800">
                                        @if ($url = $item->variant->getFirstMediaUrl('images', 'thumb'))
                                            <img src="{{ $url }}" alt="" width="56" height="56" class="w-full h-full object-cover" loading="lazy">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-gray-300"><x-ui.icon name="image" class="w-5 h-5" /></div>
                                        @endif
                                        <span class="absolute -top-1 -right-1 bg-gray-900 dark:bg-white text-white dark:text-gray-900 text-[11px] font-bold rounded-full min-w-[20px] h-5 flex items-center justify-center px-1 border-2 border-white dark:border-gray-900">{{ $item->quantity }}</span>
                                        @if ($isPre)
                                            <span class="absolute inset-x-0 bottom-0 bg-violet-600 text-white text-[9px] font-bold tracking-wide text-center py-0.5">PRE-ORDER</span>
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0 py-0.5">
                                        <p class="text-sm font-medium leading-snug line-clamp-2 text-gray-900 dark:text-white">{{ $item->variant->product->name ?? $item->variant->sku }}</p>
                                        @if ($isPre && $item->variant?->expected_available_at)
                                            <p class="text-xs font-medium text-violet-600 dark:text-violet-400 mt-0.5">ETA {{ $item->variant->expected_available_at->format('M j, Y') }}</p>
                                        @endif
                                        <p class="text-xs text-gray-500">{{ $item->variant->sku }}</p>
                                    </div>
                                    <p class="text-sm font-bold tracking-tight flex-shrink-0">{{ money((int) $item->lineTotal()) }}</p>
                                </div>
                            @endforeach
                        </div>

                        <div class="space-y-2.5 text-sm mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                            <div class="flex justify-between"><span class="text-gray-500">{{ __('Subtotal') }}</span><span class="font-medium">{{ money((int) $subtotal) }}</span></div>
                            @if ($discount > 0)
                                <div class="flex justify-between text-emerald-600 font-medium"><span>{{ __('Discount') }}</span><span>-{{ money((int) $discount) }}</span></div>
                            @endif
                            <div class="flex justify-between items-center">
                                <span class="text-gray-500">{{ __('Shipping') }}</span>
                                <span class="text-right">
                                    @if ((int) $shippingCost === 0)
                                        <span class="inline-flex items-center gap-1.5 text-emerald-600 font-bold"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> {{ __('Free') }} @if(!empty($freeReason)) <span class="hidden sm:inline font-normal text-xs text-gray-500">— {{ $freeReason }}</span> @endif</span>
                                        @if(($originalShippingCost ?? 0) > 0)
                                            <span class="ml-1 text-xs text-gray-400 line-through">{{ money((int) $originalShippingCost) }}</span>
                                        @endif
                                    @else
                                        <span class="font-bold">{{ money((int) $shippingCost) }}</span>
                                    @endif
                                </span>
                            </div>
                            @if (!empty($nextFreeThreshold) && !empty($geoName) && (int) $shippingCost !== 0)
                                <div class="rounded-xl bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900 p-3">
                                    <div class="h-1.5 bg-amber-100 dark:bg-amber-900/30 rounded-full overflow-hidden">
                                        <div class="h-full bg-amber-500 transition-all" style="width: {{ min(100, max(0, (int)(($subtotalAfterDiscount ?? 0) / max(1,(int)$nextFreeThreshold) * 100))) }}%"></div>
                                    </div>
                                    <p class="text-xs font-medium text-amber-800 dark:text-amber-200 mt-2 text-center">
                                        {{ __('Add more for free delivery') }} — {{ money(max(0, (int)$nextFreeThreshold - (int)($subtotalAfterDiscount ?? 0))) }} {{ __('Add more for free delivery') === 'Add more for free delivery' ? 'to '.$geoName : '' }}
                                    </p>
                                </div>
                            @endif
                            <div class="flex justify-between text-[16px] font-extrabold tracking-tight pt-3 border-t border-gray-200 dark:border-gray-800">
                                <span>{{ __('Total') }}</span><span>{{ money((int) ($subtotal - $discount + $shippingCost)) }}</span>
                            </div>
                            <p class="text-xs text-gray-500 text-center">{{ __('Your order is protected') }} · <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('SSL secured') }}</span></p>
                        </div>

                    </div>
                </div>

                <a href="{{ route('storefront.home') }}" class="hidden lg:flex items-center justify-center gap-1.5 text-sm text-gray-600 dark:text-gray-400 hover:text-[var(--brand)] mt-3">
                    ← {{ __('Continue shopping') }}
                </a>
            </aside>
        </div>
    </div>

    {{-- Mobile sticky CTA — above bottom nav (z-40 < nav z-50), offset for nav height + safe area --}}
    <div class="lg:hidden fixed inset-x-0 z-40 bg-white/95 dark:bg-gray-950/95 backdrop-blur border-t border-gray-200 dark:border-gray-800" style="bottom: calc(3.75rem + env(safe-area-inset-bottom, 0px));">
        <div class="px-4 pt-3 pb-3">
            <div class="flex items-center justify-between gap-3 mb-2.5">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ __('Total') }}</span>
                <span class="text-lg font-extrabold tracking-tight">{{ money((int) ($subtotal - $discount + $shippingCost)) }}</span>
            </div>
            <x-ui.button type="button" onclick="document.querySelector('form').requestSubmit()" variant="primary" size="lg" class="w-full !py-3.5 text-base font-bold shadow-soft" loading-target="placeOrder">
                {{ __('Place Order') }} — {{ money((int) ($subtotal - $discount + $shippingCost)) }}
            </x-ui.button>
            <p class="flex items-center justify-center gap-1.5 text-xs text-gray-500 mt-2"><x-ui.icon name="lock" class="w-3 h-3" /> {{ __('Secure checkout') }} · {{ __('SSL secured') }}</p>
        </div>
    </div>
</div>

<style>
.custom-scrollbar::-webkit-scrollbar{width:6px;height:6px}
.custom-scrollbar::-webkit-scrollbar-thumb{background:#E5E7EB;border-radius:999px}
.dark .custom-scrollbar::-webkit-scrollbar-thumb{background:#374151}
.safe-bottom{padding-bottom: env(safe-area-inset-bottom, 0px)}
</style>
