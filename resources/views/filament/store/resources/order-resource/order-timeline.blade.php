@php
    $events = $getState();
@endphp

<style>
    .fi-order-timeline { color: #374151; font-size: 0.875rem; }
    .fi-order-timeline-list { list-style: none; margin: 0; padding: 0; }
    .fi-order-timeline-item { display: flex; gap: 0.75rem; padding-bottom: 1.25rem; position: relative; }
    .fi-order-timeline-rail { align-items: center; display: flex; flex: 0 0 28px; flex-direction: column; position: relative; width: 28px; }
    .fi-order-timeline-connector { background: #e5e7eb; bottom: 0; position: absolute; top: 28px; width: 1px; }
    .fi-order-timeline-icon { align-items: center; border-radius: 999px; display: flex; flex: 0 0 28px; height: 28px; justify-content: center; position: relative; width: 28px; z-index: 1; }
    .fi-order-timeline-icon svg { height: 14px; width: 14px; }
    .fi-order-timeline-card { border: 1px solid #e5e7eb; border-radius: 0.5rem; flex: 1 1 auto; min-width: 0; padding: 0.625rem 0.75rem; }
    .fi-order-timeline-header { align-items: baseline; display: flex; flex-wrap: wrap; gap: 0.25rem 0.75rem; justify-content: space-between; }
    .fi-order-timeline-title { color: #111827; font-size: 0.875rem; font-weight: 600; }
    .fi-order-timeline-time { color: #6b7280; font-size: 0.75rem; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .fi-order-timeline-meta { color: #6b7280; display: flex; flex-wrap: wrap; font-size: 0.75rem; gap: 0.5rem; margin-top: 0.25rem; }
    .fi-order-timeline-description { color: #374151; font-size: 0.875rem; line-height: 1.4; margin: 0.5rem 0 0; overflow-wrap: anywhere; }
    .fi-order-timeline-empty { border: 1px dashed #d1d5db; color: #6b7280; font-size: 0.875rem; padding: 0.75rem 1rem; }
    .fi-order-timeline-sky { background: #0ea5e9; color: #fff; }
    .fi-order-timeline-emerald { background: #10b981; color: #fff; }
    .fi-order-timeline-indigo { background: #6366f1; color: #fff; }
    .fi-order-timeline-amber { background: #f59e0b; color: #fff; }
    .fi-order-timeline-fuchsia { background: #d946ef; color: #fff; }
    .fi-order-timeline-gray { background: #6b7280; color: #fff; }
    .dark .fi-order-timeline { color: #d1d5db; }
    .dark .fi-order-timeline-connector { background: rgba(255, 255, 255, 0.1); }
    .dark .fi-order-timeline-card { border-color: rgba(255, 255, 255, 0.1); }
    .dark .fi-order-timeline-title { color: #f9fafb; }
    .dark .fi-order-timeline-time,
    .dark .fi-order-timeline-meta,
    .dark .fi-order-timeline-empty { color: #9ca3af; }
    .dark .fi-order-timeline-description { color: #d1d5db; }
</style>

<div class="fi-order-timeline">
    @if ($events->isEmpty())
        <div class="fi-order-timeline-empty">No activity recorded yet.</div>
    @else
        <ol class="fi-order-timeline-list">
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
                        App\Enums\OrderEventType::StatusChanged => 'fi-order-timeline-sky',
                        App\Enums\OrderEventType::PaymentRecorded => 'fi-order-timeline-emerald',
                        App\Enums\OrderEventType::FulfillmentUpdated => 'fi-order-timeline-indigo',
                        App\Enums\OrderEventType::NoteAdded => 'fi-order-timeline-amber',
                        App\Enums\OrderEventType::ContactUpdated, App\Enums\OrderEventType::AddressUpdated => 'fi-order-timeline-fuchsia',
                        default => 'fi-order-timeline-gray',
                    };
                @endphp

                <li class="fi-order-timeline-item">
                    <div class="fi-order-timeline-rail">
                        @if (! $loop->last)
                            <span class="fi-order-timeline-connector"></span>
                        @endif
                        <span class="fi-order-timeline-icon {{ $tone }}">
                            <x-filament::icon :icon="$icon" />
                        </span>
                    </div>
                    <div class="fi-order-timeline-card">
                        <div class="fi-order-timeline-header">
                            <span class="fi-order-timeline-title">{{ $event->type?->label() }}</span>
                            <time class="fi-order-timeline-time">{{ $event->created_at?->format('M j, Y, g:i A') }}</time>
                        </div>
                        <div class="fi-order-timeline-meta">
                            <span>{{ $event->actor?->name ?? 'System' }}</span>
                            @if ($event->from_status && $event->to_status)
                                <span aria-hidden="true">•</span>
                                <span>{{ str($event->from_status)->replace('_', ' ') }} → {{ str($event->to_status)->replace('_', ' ') }}</span>
                            @endif
                        </div>
                        @if ($event->description)
                            <p class="fi-order-timeline-description">{{ $event->description }}</p>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</div>
