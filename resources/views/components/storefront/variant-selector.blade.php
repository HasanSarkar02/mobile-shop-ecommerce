@props(['useColorSwatches' => false])
{{-- Shared variant-selector primitive (F.4): the dimension option pills.
     Extracted verbatim from resources/views/storefront/products/show.blade.php.
     Reads the surrounding productDetail(...) Alpine scope through the DOM
     hierarchy: dimensions, selected, dimensionOptions(), updateVariant(). --}}
<template x-for="dimension in dimensions" :key="dimension.code">
    <div class="mt-4">
        <div class="flex items-center gap-2 mb-2">
            <p class="text-sm font-medium" x-text="dimension.label"></p>
            @if($useColorSwatches)
                <template x-if="dimension.code === 'color' || dimension.code === 'colour'">
                    <span class="text-sm font-medium text-gray-500" x-text="selected[dimension.code] ? ' - ' + selected[dimension.code] : ''"></span>
                </template>
            @endif
        </div>
        
        @if($useColorSwatches)
            <template x-if="dimension.code === 'color' || dimension.code === 'colour'">
                <div class="flex flex-wrap gap-3">
                    <template x-for="option in dimensionOptions(dimension.code)" :key="dimension.code + '-' + option">
                        <button @click="selected[dimension.code] = option; updateVariant()"
                            :aria-pressed="selected[dimension.code] === option"
                            :title="option"
                            class="w-10 h-10 rounded-full border-2 transition relative flex items-center justify-center p-0.5"
                            :class="selected[dimension.code] === option ? 'border-[var(--brand)]' : 'border-transparent hover:border-gray-300 dark:hover:border-gray-700'">
                            <span class="block w-full h-full rounded-full border border-black/10 dark:border-white/10"
                                  :style="`background-color: ${window._productColorMap?.[option] || option.replace(/[^a-zA-Z]/g, '').toLowerCase()}`">
                            </span>
                        </button>
                    </template>
                </div>
            </template>
            <template x-if="dimension.code !== 'color' && dimension.code !== 'colour'">
                <div class="flex flex-wrap gap-2">
                    <template x-for="option in dimensionOptions(dimension.code)" :key="dimension.code + '-' + option">
                        <button @click="selected[dimension.code] = option; updateVariant()"
                            :aria-pressed="selected[dimension.code] === option"
                            class="px-3 py-1.5 rounded-full border text-sm transition"
                            :class="selected[dimension.code] === option ?
                                'border-[var(--brand)] text-[var(--brand)] bg-[var(--brand)]/10 font-semibold' :
                                'border-gray-300 dark:border-gray-700 hover:border-gray-400'"
                            x-text="option + (dimension.suffix || '')"></button>
                    </template>
                </div>
            </template>
        @else
            <div class="flex flex-wrap gap-2">
                <template x-for="option in dimensionOptions(dimension.code)"
                    :key="dimension.code + '-' + option">
                    <button @click="selected[dimension.code] = option; updateVariant()"
                        :aria-pressed="selected[dimension.code] === option"
                        class="px-3 py-1.5 rounded-full border text-sm transition"
                        :class="selected[dimension.code] === option ?
                            'border-[var(--brand)] text-[var(--brand)] bg-[var(--brand)]/10 font-semibold' :
                            'border-gray-300 dark:border-gray-700 hover:border-gray-400'"
                        x-text="option + (dimension.suffix || '')"></button>
                </template>
            </div>
        @endif
    </div>
</template>
