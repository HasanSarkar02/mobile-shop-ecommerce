@props(['title', 'description' => null, 'actionLabel' => null, 'actionUrl' => null])
<div class="text-center py-16">
    <p class="text-lg font-medium text-gray-700 dark:text-gray-300">{{ $title }}</p>
    @if ($description)
        <p class="text-sm text-gray-500 mt-1">{{ $description }}</p>
    @endif
    @if ($actionLabel && $actionUrl)
        <x-ui.button as="a" href="{{ $actionUrl }}" class="mt-4 inline-flex"
            tag="a">{{ $actionLabel }}</x-ui.button>
    @endif
</div>
