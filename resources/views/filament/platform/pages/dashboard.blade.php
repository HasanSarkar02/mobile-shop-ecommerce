<x-filament-panels::page class="fi-dashboard-page">
    @php($alerts = $this->getOperationalAlerts())
    @php($dnsAlerts = $this->getDnsHealthAlerts())
    @php($actions = [...$alerts, ...$dnsAlerts])
    @php($hasDnsAlerts = count($dnsAlerts) > 0)

    <section class="mt-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Action Required</h2>
                @if (count($actions) > 0)
                    <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-500/10 dark:text-rose-400">
                        {{ count($actions) }}
                    </span>
                @endif
            </div>

            @if (count($actions) > 0)
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($actions as $action)
                        <div class="flex flex-col rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="min-w-0 text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $action['label'] }}</h3>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold {{ $action['tone'] === 'danger' ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400' }}">
                                    {{ $action['count'] }}
                                </span>
                            </div>

                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $action['description'] }}</p>

                            @if (($action['detail'] ?? null) !== null)
                                <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $action['detail'] }}</p>
                            @endif

                            <a href="{{ $action['url'] }}"
                                class="mt-3 inline-flex w-fit items-center gap-1 rounded-md bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
                                {{ $action['view_label'] ?? 'View' }}
                                <span aria-hidden="true">&rarr;</span>
                            </a>

                            @if (count($action['domains'] ?? []) > 0)
                                <ul class="mt-3 divide-y divide-gray-100 border-t border-gray-100 dark:divide-gray-800 dark:border-gray-800">
                                    @foreach ($action['domains'] as $domain)
                                        <li class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2 text-xs text-gray-600 dark:text-gray-300">
                                            <span class="font-medium text-gray-800 dark:text-gray-100">{{ $domain['domain'] }}</span>
                                            <span>{{ $domain['tenant'] }}</span>
                                            @if ($domain['failure_code'] !== null)
                                                <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">{{ $domain['failure_code'] }}</span>
                                            @endif
                                            @if ($domain['failure_message'] !== null)
                                                <span>{{ $domain['failure_message'] }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if (! $hasDnsAlerts)
                    <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">All domains are verified and healthy.</p>
                @endif
            @else
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">Everything is up to date.</p>
            @endif
        </div>
    </section>

    @php($system = $this->getSystemHealth())
    @php($oldestAge = $system['queue']['oldest_pending_age_seconds'])
    @php($latestFailed = $system['queue']['recent_failed_jobs'][0] ?? null)
    @php($heartbeatAge = $system['scheduler']['age_seconds'])

    <section class="mt-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">System Health</h2>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full {{ $system['queue']['failed_jobs_count'] > 0 ? 'bg-rose-500' : 'bg-emerald-500' }}"></span>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Failed Jobs</h3>
                    </div>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $system['queue']['failed_jobs_count'] }}</p>
                    @if ($latestFailed !== null)
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $latestFailed['exception'] }}</p>
                    @else
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">No failed jobs.</p>
                    @endif
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full {{ $system['queue']['pending_jobs_count'] > 0 ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Queue Backlog</h3>
                    </div>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $system['queue']['pending_jobs_count'] }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        @if ($oldestAge !== null)
                            Oldest queued {{ now()->subSeconds($oldestAge)->diffForHumans(['parts' => 1]) }} ago
                        @else
                            Queue is clear.
                        @endif
                    </p>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full {{ $system['scheduler']['status'] === 'healthy' ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Scheduler</h3>
                    </div>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ ucfirst($system['scheduler']['status']) }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        @if ($heartbeatAge !== null)
                            Last beat {{ now()->subSeconds($heartbeatAge)->diffForHumans(['parts' => 1]) }} ago
                        @else
                            No heartbeat yet.
                        @endif
                    </p>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full {{ $system['app']['database'] === 'OK' ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Database</h3>
                    </div>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $system['app']['database'] }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $system['app']['environment'] }} environment</p>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full {{ $system['app']['cache'] === 'OK' ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cache</h3>
                    </div>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $system['app']['cache'] }}</p>
                    @if ($system['app']['version'] !== null)
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">App v{{ $system['app']['version'] }}</p>
                    @else
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Cache store responding</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    @php($recentActivity = $this->getRecentActivity())

    <section class="mt-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">Recent Platform Activity</h2>

            @if (count($recentActivity) > 0)
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                <th class="py-2 pr-4 font-medium">Time</th>
                                <th class="py-2 pr-4 font-medium">Type</th>
                                <th class="py-2 pr-4 font-medium">Tenant</th>
                                <th class="py-2 pr-4 font-medium">Action</th>
                                <th class="py-2 pr-4 font-medium">Actor</th>
                                <th class="py-2 font-medium">Note / Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($recentActivity as $entry)
                                <tr>
                                    <td class="whitespace-nowrap py-3 pr-4 text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ $entry['time_label'] }}</td>
                                    <td class="py-3 pr-4">
                                        <span class="whitespace-nowrap rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $entry['badge'] }}</span>
                                    </td>
                                    <td class="py-3 pr-4 font-medium text-gray-800 dark:text-gray-100">{{ $entry['tenant'] }}</td>
                                    <td class="py-3 pr-4 text-gray-600 dark:text-gray-300">
                                        @if ($entry['url'] !== null)
                                            <a href="{{ $entry['url'] }}"
                                                class="text-gray-700 underline underline-offset-2 transition hover:text-gray-900 dark:text-gray-200 dark:hover:text-white">{{ $entry['label'] }}</a>
                                        @else
                                            {{ $entry['label'] }}
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap py-3 pr-4 text-xs text-gray-500 dark:text-gray-400">{{ $entry['actor'] }}</td>
                                    <td class="py-3 text-xs text-gray-500 dark:text-gray-400">{{ $entry['note'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">No recent platform activity.</p>
            @endif
        </div>
    </section>

    <section class="mt-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">Quick Actions</h2>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($this->getQuickLinks() as $link)
                    <a href="{{ $link['url'] }}"
                        class="flex items-center gap-3 rounded-lg border border-gray-200 p-4 text-sm font-medium text-gray-700 transition hover:border-gray-400 hover:bg-gray-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 dark:border-gray-800 dark:text-gray-200 dark:hover:border-gray-600 dark:hover:bg-gray-800">
                        <x-filament::icon :icon="$link['icon']" class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500" />
                        <span>{{ $link['label'] }}</span>
                        <span aria-hidden="true" class="ml-auto text-gray-400">&rarr;</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
</x-filament-panels::page>