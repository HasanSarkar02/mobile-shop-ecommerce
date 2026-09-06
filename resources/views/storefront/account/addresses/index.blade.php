@extends('storefront.account.layout')

@section('title', __('My Addresses') . ' - ' . tenant()->name)

@section('account-content')
    <h1 class="text-xl font-bold tracking-tight mb-1">{{ __('My Addresses') }}</h1>
    <p class="text-sm text-gray-500 mb-5">{{ __('Exact delivery charge calculated at checkout based on your address.') }}</p>

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900 text-emerald-800 dark:text-emerald-200 text-sm px-4 py-3">{{ session('status') }}</div>
    @endif

    <div class="space-y-3">
        @forelse ($addresses as $address)
            <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 flex justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $address->recipient_name }} <span class="font-normal text-gray-500">· {{ $address->phone }}</span></p>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-0.5 leading-relaxed">{{ $address->address_line_1 }}@if($address->area), {{ $address->area }}@endif</p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ $address->city }}
                        @if($address->division) — {{ $address->division->name_en }} @if($address->district) → {{ $address->district->name_en }} @endif @endif
                    </p>
                    @if($address->bd_division_id)
                        <span class="inline-flex mt-2 text-xs bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-2 py-1 rounded-full">{{ $address->division?->name_en ?? '' }} @if($address->district) → {{ $address->district->name_en }} @endif</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('storefront.account.addresses.destroy', $address) }}" class="flex-shrink-0">@csrf
                    @method('DELETE')<button class="text-xs font-semibold text-red-600 hover:text-red-700 bg-red-50 dark:bg-red-950/30 px-3 py-1.5 rounded-full border border-red-200 dark:border-red-900">{{ __('Remove') }}</button></form>
            </div>
        @empty
            <div class="rounded-2xl border border-dashed border-gray-200 dark:border-gray-700 p-6 text-center">
                <p class="text-sm text-gray-500">{{ __('No saved addresses yet.') }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ __('Add your delivery address with Division → District → Upazila for accurate shipping rate.') }}</p>
            </div>
        @endforelse
    </div>

    @php
        $divisions = \App\Models\BdDivision::orderBy('name_en')->get();
    @endphp
    <form method="POST" action="{{ route('storefront.account.addresses.store') }}" class="mt-8 bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-5 sm:p-6 space-y-4" x-data="{
        division: '{{ old('bd_division_id') }}',
        district: '{{ old('bd_district_id') }}',
        upazila: '{{ old('bd_upazila_id') }}',
        districts: [],
        upazilas: [],
        async loadDistricts() {
            if(!this.division) { this.districts=[]; this.upazilas=[]; return; }
            const res = await fetch('{{ route('storefront.bd.districts') }}?division_id=' + this.division);
            this.districts = await res.json();
        },
        async loadUpazilas() {
            if(!this.district) { this.upazilas=[]; return; }
            const res = await fetch('{{ route('storefront.bd.upazilas') }}?district_id=' + this.district);
            this.upazilas = await res.json();
        }
    }" x-init="if(division) loadDistricts().then(()=>{ if(district) loadUpazilas() })">
        @csrf
        <input type="hidden" name="type" value="shipping">
        <h2 class="text-sm font-bold tracking-tight">{{ __('Add new address') }}</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-[12px] font-bold tracking-wide uppercase text-gray-500 dark:text-gray-400 mb-1.5 ml-1">{{ __('Recipient name') }} *</label>
                <input name="recipient_name" value="{{ old('recipient_name') }}" placeholder="{{ __('e.g. Karim Ahmed') }}" class="w-full h-11 px-3.5 rounded-xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-sm shadow-sm focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 outline-none @error('recipient_name') border-red-300 dark:border-red-800 @enderror" required>
                @error('recipient_name') <p class="text-xs text-red-600 mt-1.5 ml-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-[12px] font-bold tracking-wide uppercase text-gray-500 dark:text-gray-400 mb-1.5 ml-1">{{ __('Phone') }} *</label>
                <input name="phone" value="{{ old('phone') }}" placeholder="01XXXXXXXXX" class="w-full h-11 px-3.5 rounded-xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-sm shadow-sm focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 outline-none @error('phone') border-red-300 @enderror" required>
                @error('phone') <p class="text-xs text-red-600 mt-1.5 ml-1">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[12px] font-bold tracking-wide uppercase text-gray-500 dark:text-gray-400 mb-1.5 ml-1">{{ __('Address') }} *</label>
                <input name="address_line_1" value="{{ old('address_line_1') }}" placeholder="{{ __('House, Road, Area, Thana') }}" class="w-full h-11 px-3.5 rounded-xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-sm shadow-sm focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 outline-none @error('address_line_1') border-red-300 @enderror" required>
                @error('address_line_1') <p class="text-xs text-red-600 mt-1.5 ml-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-[12px] font-bold tracking-wide uppercase text-gray-500 dark:text-gray-400 mb-1.5 ml-1">{{ __('Division') }} *</label>
                <select name="bd_division_id" x-model="division" @change="district=''; upazilas=[]; loadDistricts()" class="w-full h-11 px-3.5 rounded-xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-sm shadow-sm focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 outline-none @error('bd_division_id') border-red-300 @enderror" required>
                    <option value="">{{ __('Select Division') }}</option>
                    @foreach($divisions as $division)
                        <option value="{{ $division->id }}" @selected(old('bd_division_id')==$division->id)>{{ $division->name_en }} — {{ $division->name_bn }}</option>
                    @endforeach
                </select>
                @error('bd_division_id') <p class="text-xs text-red-600 mt-1.5 ml-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-[12px] font-bold tracking-wide uppercase text-gray-500 dark:text-gray-400 mb-1.5 ml-1">{{ __('District') }} *</label>
                <select name="bd_district_id" x-model="district" @change="loadUpazilas()" class="w-full h-11 px-3.5 rounded-xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-sm shadow-sm focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 outline-none @error('bd_district_id') border-red-300 @enderror" required>
                    <option value="">{{ __('Select District') }}</option>
                    <template x-for="d in districts" :key="d.id">
                        <option :value="d.id" x-text="d.name_en + ' — ' + d.name_bn" :selected="district==d.id"></option>
                    </template>
                    @if(old('bd_district_id'))
                        <option value="{{ old('bd_district_id') }}" selected>{{ __('District') }}</option>
                    @endif
                </select>
                @error('bd_district_id') <p class="text-xs text-red-600 mt-1.5 ml-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-[12px] font-bold tracking-wide uppercase text-gray-500 dark:text-gray-400 mb-1.5 ml-1">{{ __('Upazila') }}</label>
                <select name="bd_upazila_id" x-model="upazila" class="w-full h-11 px-3.5 rounded-xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-sm shadow-sm focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 outline-none">
                    <option value="">{{ __('Select Upazila') }}</option>
                    <template x-for="u in upazilas" :key="u.id">
                        <option :value="u.id" x-text="u.name_en + ' — ' + u.name_bn" :selected="upazila==u.id"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-[12px] font-bold tracking-wide uppercase text-gray-500 dark:text-gray-400 mb-1.5 ml-1">{{ __('City') }} *</label>
                <input name="city" value="{{ old('city', 'Dhaka') }}" placeholder="e.g. Dhaka" class="w-full h-11 px-3.5 rounded-xl bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 text-sm shadow-sm focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 outline-none @error('city') border-red-300 @enderror" required>
                @error('city') <p class="text-xs text-red-600 mt-1.5 ml-1">{{ $message }}</p> @enderror
            </div>
        </div>
        <button class="w-full h-11 rounded-xl bg-[var(--brand)] text-white font-bold shadow-sm hover:brightness-110 transition">{{ __('Add Address') }}</button>
        <p class="text-xs text-gray-500 text-center">{{ __('Delivery charge will be calculated based on your Division → District → Upazila') }}</p>
    </form>
@endsection
