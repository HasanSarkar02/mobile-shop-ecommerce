@props(['label' => null, 'name', 'error' => null, 'hint' => null])
@php
    // Auto-resolve error from Livewire/Validator error bag if not explicitly passed
    $resolvedError = $error;
    if ($resolvedError === null && isset($errors) && $errors->has($name)) {
        $resolvedError = $errors->first($name);
    }
    // Also check for dot notation alternative (Livewire sometimes uses guestAddress.recipient_name)
    if ($resolvedError === null && isset($errors)) {
        // Try with brackets notation fallback
        $altName = str_replace('.', '_', $name);
        if ($errors->has($altName)) {
            $resolvedError = $errors->first($altName);
        }
    }
@endphp
<div class="group">
    @if ($label)
        <label for="{{ $name }}"
            class="block text-[12px] font-bold tracking-wide uppercase {{ $resolvedError ? 'text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400 group-focus-within:text-[var(--brand)]' }} mb-1.5 ml-1 transition-colors">{{ $label }}</label>
    @endif
    <div class="relative">
        <input id="{{ $name }}" name="{{ $name }}"
            {{ $attributes->merge(['class' => 'peer w-full h-11 px-3.5 rounded-xl bg-white dark:bg-gray-950 border text-sm text-gray-900 dark:text-white placeholder:text-gray-400 dark:placeholder:text-gray-500 shadow-sm transition-all duration-200 outline-none
            ' . ($resolvedError
                ? 'border-red-300 dark:border-red-800 bg-red-50/50 dark:bg-red-950/20 focus:border-red-500 focus:ring-4 focus:ring-red-500/10'
                : 'border-gray-200 dark:border-gray-800 hover:border-gray-300 dark:hover:border-gray-700 focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 focus:bg-white dark:focus:bg-gray-900')]) }}>
        @if($resolvedError)
            <span class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-red-500 text-white flex items-center justify-center pointer-events-none">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
        @endif
    </div>
    @if ($resolvedError)
        <p class="text-xs font-medium text-red-600 dark:text-red-400 mt-1.5 ml-1 flex items-center gap-1"><span class="w-1 h-1 rounded-full bg-red-600"></span>{{ $resolvedError }}</p>
    @elseif($hint)
        <p class="text-xs text-gray-500 mt-1.5 ml-1">{{ $hint }}</p>
    @endif
</div>
