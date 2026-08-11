@props(['variant' => 'primary', 'size' => 'md', 'type' => 'button', 'loadingTarget' => null, 'as' => 'button'])
@php
    $variants = [
        'primary' =>
            'bg-[var(--brand)] text-white hover:brightness-110 focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[var(--brand)]',
        'secondary' =>
            'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-700',
        'danger' => 'bg-red-600 text-white hover:bg-red-700',
        'ghost' => 'bg-transparent text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800',
    ];
    $sizes = ['sm' => 'px-3 py-1.5 text-sm', 'md' => 'px-4 py-2.5 text-sm', 'lg' => 'px-6 py-3 text-base'];
    $classes = 'inline-flex items-center justify-center gap-2 rounded-xl font-medium transition disabled:opacity-50 disabled:cursor-not-allowed outline-none ' . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']);
@endphp
@if ($as === 'a')
    <a {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}
        @if ($loadingTarget) wire:loading.attr="disabled" wire:target="{{ $loadingTarget }}" @endif>
        @if ($loadingTarget)
            <svg wire:loading wire:target="{{ $loadingTarget }}" class="animate-spin h-4 w-4" viewBox="0 0 24 24"
                fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
            </svg>
        @endif
        {{ $slot }}
    </button>
@endif
