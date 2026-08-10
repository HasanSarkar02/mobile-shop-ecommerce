@props(['variant' => 'neutral'])
@php
    $variants = [
        'neutral' => 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300',
        'success' => 'bg-green-100 dark:bg-green-950 text-green-700 dark:text-green-300',
        'warning' => 'bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300',
        'danger' => 'bg-red-100 dark:bg-red-950 text-red-700 dark:text-red-300',
    ];
@endphp
<span
    {{ $attributes->merge(['class' => 'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ' . ($variants[$variant] ?? $variants['neutral'])]) }}>
    {{ $slot }}
</span>
