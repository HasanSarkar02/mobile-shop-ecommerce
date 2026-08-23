@props(['endsAt'])

@if ($endsAt)
    <div x-data="offerCountdown(@js($endsAt->getTimestampMs()))">
        <noscript>
            <p class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-600 dark:bg-red-950 dark:text-red-400">
                Ends {{ $endsAt->format('M j, Y g:i A') }}
            </p>
        </noscript>

        <div x-show="live" x-cloak class="flex items-center gap-3">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Ends in</span>
            <div class="flex items-start gap-1.5" role="timer" aria-label="Time remaining">
                @foreach (['days' => 'Days', 'hours' => 'Hours', 'minutes' => 'Mins', 'seconds' => 'Secs'] as $key => $label)
                    @if (! $loop->first)
                        <span class="pt-1 text-base font-bold leading-none text-[var(--brand)]" aria-hidden="true">:</span>
                    @endif
                    <div class="flex flex-col items-center gap-1">
                        <span x-text="{{ $key }}"
                            class="min-w-[2.75rem] rounded-lg border border-black/5 bg-white px-2 py-1.5 text-center text-lg font-bold tabular-nums text-gray-900 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-50">00</span>
                        <span class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <p x-show="!live" x-cloak
            class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500 dark:bg-gray-800 dark:text-gray-400">
            This offer has ended.
        </p>
    </div>
@endif
