@php
    $events = $getState();
@endphp

<ol class="relative">
    @foreach ($events as $event)
        @php
            $icon = match ($event->type) {
                App\Enums\OrderEventType::StatusChanged => 'heroicon-o-arrows-right-left',
                App\Enums\OrderEventType::PaymentRecorded => 'heroicon-o-banknotes',
                App\Enums\OrderEventType::FulfillmentUpdated => 'heroicon-o-truck',
                App\Enums\OrderEventType::NoteAdded => 'heroicon-o-chat-bubble-bottom-center-text',
                App\Enums\OrderEventType::ContactUpdated => 'heroicon-o-user',
                App\Enums\OrderEventType::AddressUpdated => 'heroicon-o-map-pin',
                default => 'heroicon-o-plus-circle',
            };
            $tone = match ($event->type) {
                App\Enums\OrderEventType::StatusChanged => 'bg-sky-500 text-white',
                App\Enums\OrderEventType::PaymentRecorded => 'bg-emerald-500 text-white',
                App\Enums\OrderEventType::FulfillmentUpdated => 'bg-indigo-500 text-white',
                App\Enums\OrderEventType::NoteAdded => 'bg-amber-500 text-white',
                App\Enums\OrderEventType::ContactUpdated, App\Enums\OrderEventType::AddressUpdated => 'bg-fuchsia-500 text-white',
                default => 'bg-gray-500 text-white',
            };
        @endphp

        <li class="relative flex gap-4 pb-7 last:pb-0">
            <div class="flex flex-col items-center">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $tone }} shadow-sm">
                    <x-filament::icon :icon="$icon" class="h-4 w-4" />
                </span>
                @if (! $loop->last)
                    <span class="mt-1 w-px flex-1 bg-gray-200 dark:bg-white/10"></span>
                @endif
            </div>

            <div class="min-w-0 flex-1 pb-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="font-medium text-gray-950 dark:text-white">
                        {{ $event->type?->label() }}
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $event->created_at?->diffForHumans() }}
                    </span>
                </div>

                @if ($event->description)
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $event->description }}</p>
                @endif

                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    @if ($event->from_status && $event->to_status)
                        <span class="capitalize">{{ str($event->from_status)->replace('_', ' ') }}</span>
                        <span class="mx-1">→</span>
                        <span class="capitalize">{{ str($event->to_status)->replace('_', ' ') }}</span>
                    @endif
                    @if ($event->actor)
                        @if ($event->from_status && $event->to_status)<span class="mx-1 text-gray-300 dark:text-gray-600">·</span>@endif
                        {{ $event->actor->name }}
                    @endif
                </p>
            </div>
        </li>
    @endforeach
</ol>