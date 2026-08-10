@props(['label', 'name', 'checked' => false])
<label class="flex items-center gap-2 text-sm cursor-pointer select-none py-0.5">
    <input
        type="checkbox"
        name="{{ $name }}"
        @checked($checked)
        {{ $attributes->merge(['class' => 'rounded border-gray-300 dark:border-gray-700 text-[var(--brand)] focus:ring-[var(--brand)] transition']) }}
    >
    <span>{{ $label }}</span>
</label>