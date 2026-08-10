@php
    $chips = collect();
    if ($ids = request('brand')) {
        foreach ((array) $ids as $id) {
            if ($brand = \App\Models\Brand::find($id)) {
                $chips->push(['label' => $brand->name, 'query' => request()->except('brand')]);
            }
        }
    }
    $flags = [
        'on_sale' => 'On Sale',
        'in_stock' => 'In Stock',
        'emi' => 'EMI Available',
        'warranty' => 'Warranty',
        'new_arrival' => 'New Arrival',
        'official' => 'Official Product',
    ];
    foreach ($flags as $key => $label) {
        if (request($key)) {
            $chips->push(['label' => $label, 'query' => request()->except($key)]);
        }
    }
    if (request('price_min') || request('price_max')) {
        $chips->push(['label' => 'Price Range', 'query' => request()->except(['price_min', 'price_max'])]);
    }
@endphp
@if ($chips->isNotEmpty())
    <div class="flex flex-wrap items-center gap-2 mb-4">
        @foreach ($chips as $chip)
            <a href="{{ url()->current() }}?{{ http_build_query($chip['query']) }}"
                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-sm hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                {{ $chip['label'] }} <span class="text-gray-400">✕</span>
            </a>
        @endforeach
        <a href="{{ url()->current() }}" class="text-sm text-[var(--brand)] font-medium px-1">Clear all</a>
    </div>
@endif
