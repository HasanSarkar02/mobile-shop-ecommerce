@php
    $defaults = [
        ['icon' => 'grid', 'label' => 'Official Products', 'sub' => '100% Authentic'],
        ['icon' => 'phone', 'label' => '0% EMI Available', 'sub' => 'On select cards'],
        ['icon' => 'cart', 'label' => 'Fast Delivery', 'sub' => 'Nationwide shipping'],
        ['icon' => 'user', 'label' => 'Secure Payment', 'sub' => 'Cash, card & mobile banking'],
    ];
    $items = $section->config['items'] ?? $defaults;
@endphp
@if (!empty($items))
    <div
        class="rounded-2xl border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/50 py-6 px-4 sm:px-8">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
            @foreach ($items as $item)
                <div class="flex items-center gap-3">
                    <span
                        class="flex-shrink-0 w-10 h-10 rounded-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 flex items-center justify-center text-[var(--brand)]">
                        <x-ui.icon :name="$item['icon'] ?? 'grid'" class="w-5 h-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $item['label'] }}
                        </p>
                        @if (!empty($item['sub']))
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $item['sub'] }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
