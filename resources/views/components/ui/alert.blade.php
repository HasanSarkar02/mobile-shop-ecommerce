@props(['variant' => 'info', 'dismissible' => false])
@php
    $variants = [
        'info' => 'bg-blue-50 dark:bg-blue-950 text-blue-800 dark:text-blue-200 border-blue-200 dark:border-blue-900',
        'success' =>
            'bg-green-50 dark:bg-green-950 text-green-800 dark:text-green-200 border-green-200 dark:border-green-900',
        'warning' =>
            'bg-amber-50 dark:bg-amber-950 text-amber-800 dark:text-amber-200 border-amber-200 dark:border-amber-900',
        'danger' => 'bg-red-50 dark:bg-red-950 text-red-800 dark:text-red-200 border-red-200 dark:border-red-900',
    ];
@endphp
<div @if ($dismissible) x-data="{ show: true }" x-show="show" @endif
    {{ $attributes->merge(['class' => 'rounded-xl border p-4 text-sm relative ' . ($variants[$variant] ?? $variants['info'])]) }}>
    {{ $slot }}
    @if ($dismissible)
        <button @click="show = false" class="absolute top-3 right-3 opacity-60 hover:opacity-100">✕</button>
    @endif
</div>
