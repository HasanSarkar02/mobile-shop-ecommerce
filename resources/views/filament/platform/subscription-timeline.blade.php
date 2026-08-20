@php
    $entries = $getState() ?? [];
    $currentDay = null;
@endphp

@if (count($entries) === 0)
    <div class="text-sm text-gray-500 dark:text-gray-400">
        No subscription activity recorded yet.
    </div>
@else
    <ol class="relative">
        @foreach ($entries as $entry)
            @php
                $icon = match ($entry['kind']) {
                    'payment' => 'heroicon-o-banknotes',
                    'decision' => 'heroicon-o-arrows-right-left',
                    default => 'heroicon-o-clock',
                };
                $tone = match ($entry['kind']) {
                    'payment' => match ($entry['status_value'] ?? null) {
                        'verified' => 'bg-emerald-500 text-white',
                        'rejected' => 'bg-rose-500 text-white',
                        default => 'bg-amber-500 text-white',
                    },
                    'decision' => $entry['action'] === 'approved' ? 'bg-sky-500 text-white' : 'bg-rose-500 text-white',
                    default => 'bg-gray-500 text-white',
                };
            @endphp

            @if ($currentDay !== $entry['day'])
                <li class="mb-2 mt-6 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500 {{ $loop->first ? 'mt-0' : '' }}">
                    {{ $entry['day'] }}
                </li>
                @php
                    $currentDay = $entry['day'];
                @endphp
            @endif

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
                        <span class="font-medium text-gray-950 dark:text-white">{{ $entry['label'] }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $entry['time_label'] }}</span>
                    </div>

                    @if ($entry['kind'] === 'event' && ($entry['from_plan'] || $entry['to_plan']))
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            @if ($entry['from_plan'] && $entry['to_plan'])
                                <span>{{ $entry['from_plan'] }}</span>
                                <span class="mx-1">→</span>
                                <span>{{ $entry['to_plan'] }}</span>
                            @elseif ($entry['to_plan'])
                                <span class="mx-1">→</span>
                                <span>{{ $entry['to_plan'] }}</span>
                            @else
                                <span>{{ $entry['from_plan'] }}</span>
                            @endif
                        </p>
                    @endif

                    @if ($entry['kind'] === 'payment')
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            {{ $entry['intent'] }}
                            <span class="mx-1">·</span>
                            ৳{{ number_format($entry['amount'] / 100, 2) }}
                            <span class="mx-1">·</span>
                            {{ $entry['reference'] }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ $entry['provider'] }}</span>
                            @if ($entry['payment_method'])
                                <span class="mx-1">·</span><span>{{ $entry['payment_method'] }}</span>
                            @endif
                            @if ($entry['verifier'])
                                <span class="mx-1">·</span><span>Verified by {{ $entry['verifier'] }}</span>
                            @endif
                            @if ($entry['rejector'])
                                <span class="mx-1">·</span><span>Rejected by {{ $entry['rejector'] }}</span>
                            @endif
                        </p>
                        @if ($entry['rejected_reason'])
                            <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">
                                Rejection reason: {{ $entry['rejected_reason'] }}
                            </p>
                        @endif
                        @if ($entry['url'])
                            <a href="{{ $entry['url'] }}" class="mt-1 inline-flex text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">
                                View payment
                            </a>
                        @endif
                    @endif

                    @if ($entry['kind'] === 'decision')
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            @if ($entry['requested_plan'])
                                Requested plan: {{ $entry['requested_plan'] }}
                            @else
                                Requested plan change
                            @endif
                        </p>
                    @endif

                    @if ($entry['kind'] === 'event')
                        @if ($entry['note'])
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $entry['note'] }}</p>
                        @endif
                        @if (! empty($entry['metadata']))
                            @php
                                $chips = [];
                                if (isset($entry['metadata']['extension_days'])) {
                                    $chips[] = '+' . $entry['metadata']['extension_days'] . ' days';
                                }
                                if (isset($entry['metadata']['trial_days'])) {
                                    $chips[] = $entry['metadata']['trial_days'] . ' day trial';
                                }
                                if (isset($entry['metadata']['previous_status'])) {
                                    $chips[] = 'was ' . str_replace('_', ' ', (string) $entry['metadata']['previous_status']);
                                }
                                if (isset($entry['metadata']['billing_period'])) {
                                    $chips[] = $entry['metadata']['billing_period'];
                                }
                            @endphp
                            @if ($chips !== [])
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($chips as $chip)
                                        <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                            {{ $chip }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    @endif

                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $entry['actor'] }}</p>
                </div>
            </li>
        @endforeach
    </ol>
@endif