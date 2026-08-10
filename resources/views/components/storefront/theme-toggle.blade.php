@props(['class' => 'p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition', 'showLabel' => false])
<button type="button" x-data="{ dark: document.documentElement.classList.contains('dark') }"
    @click="
        dark = !dark;
        document.documentElement.classList.toggle('dark', dark);
        localStorage.theme = dark ? 'dark' : 'light';
    "
    {{ $attributes->merge(['class' => $class]) }} aria-label="Toggle dark mode">
    <span x-show="!dark" x-cloak class="flex items-center gap-3">
        <x-ui.icon name="moon" class="w-5 h-5 {{ $showLabel ? 'text-gray-400' : '' }}" />
        @if ($showLabel)
            <span>Dark Mode</span>
        @endif
    </span>
    <span x-show="dark" x-cloak class="flex items-center gap-3">
        <x-ui.icon name="sun" class="w-5 h-5 {{ $showLabel ? 'text-gray-400' : '' }}" />
        @if ($showLabel)
            <span>Light Mode</span>
        @endif
    </span>
    <noscript><x-ui.icon name="moon" class="w-5 h-5" /></noscript>
</button>
