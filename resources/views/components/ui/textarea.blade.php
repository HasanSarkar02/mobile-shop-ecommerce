@props(['label' => null, 'name', 'error' => null, 'rows' => 3])
<div>
    @if ($label)
        <label for="{{ $name }}"
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $label }}</label>
    @endif
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}"
        {{ $attributes->merge(['class' => 'w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 focus:border-[var(--brand)] focus:ring-[var(--brand)] transition ' . ($error ? 'border-red-500 focus:border-red-500' : '')]) }}>{{ $slot }}</textarea>
    @if ($error)
        <p class="text-sm text-red-500 mt-1">{{ $error }}</p>
    @endif
</div>
