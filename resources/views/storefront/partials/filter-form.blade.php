@php
    $preserved = collect(
        request()->except([
            'brand',
            'on_sale',
            'in_stock',
            'emi',
            'warranty',
            'new_arrival',
            'official',
            'price_min',
            'price_max',
            'attr',
            'page',
        ]),
    );
@endphp
<form method="GET" class="text-sm">
    @foreach ($preserved as $key => $val)
        <input type="hidden" name="{{ $key }}" value="{{ $val }}">
    @endforeach
    <input type="hidden" name="sort" value="{{ $filters->sort }}">

    @if (($result['facets']['brands'] ?? collect())->isNotEmpty())
        <div x-data="{ open: true }" class="border-b border-gray-100 dark:border-gray-800 py-3">
            <button type="button" @click="open = !open" class="w-full flex justify-between items-center font-medium">
                Brand <span x-text="open ? '−' : '+'" class="text-gray-400"></span>
            </button>
            <div x-show="open" x-collapse class="mt-2 space-y-0.5">
                @foreach ($result['facets']['brands'] as $brand)
                    <x-ui.checkbox name="brand[]" value="{{ $brand->id }}" :label="$brand->name . ' (' . $brand->products_count . ')'" :checked="in_array($brand->id, $filters->brandIds)"
                        onchange="this.form.submit()" />
                @endforeach
            </div>
        </div>
    @endif

    <div x-data="{ open: true }" class="border-b border-gray-100 dark:border-gray-800 py-3">
        <button type="button" @click="open = !open" class="w-full flex justify-between items-center font-medium">
            Availability & Offers <span x-text="open ? '−' : '+'" class="text-gray-400"></span>
        </button>
        <div x-show="open" x-collapse class="mt-2 space-y-0.5">
            <x-ui.checkbox name="in_stock" value="1" label="In Stock" :checked="$filters->inStockOnly"
                onchange="this.form.submit()" />
            <x-ui.checkbox name="on_sale" value="1" label="On Sale" :checked="$filters->onSaleOnly"
                onchange="this.form.submit()" />
            <x-ui.checkbox name="emi" value="1" label="EMI Available" :checked="$filters->emiOnly"
                onchange="this.form.submit()" />
            <x-ui.checkbox name="warranty" value="1" label="Warranty" :checked="$filters->warrantyOnly"
                onchange="this.form.submit()" />
            <x-ui.checkbox name="new_arrival" value="1" label="New Arrival" :checked="$filters->newArrivalOnly"
                onchange="this.form.submit()" />
            <x-ui.checkbox name="official" value="1" label="Official Product" :checked="$filters->officialOnly"
                onchange="this.form.submit()" />
        </div>
    </div>

    @foreach ($result['facets']['attributes'] as $code => $facet)
        <div x-data="{ open: true }" class="border-b border-gray-100 dark:border-gray-800 py-3">
            <button type="button" @click="open = !open" class="w-full flex justify-between items-center font-medium">
                {{ $facet['label'] }} <span x-text="open ? '−' : '+'" class="text-gray-400"></span>
            </button>
            <div x-show="open" x-collapse class="mt-2 space-y-0.5">
                @foreach ($facet['options'] as $option)
                    <x-ui.checkbox name="attr[{{ $code }}][]" value="{{ $option['value'] }}" :label="$option['value'] . ' (' . $option['count'] . ')'"
                        :checked="in_array($option['value'], $filters->attributes[$code] ?? [])" onchange="this.form.submit()" />
                @endforeach
            </div>
        </div>
    @endforeach
</form>
