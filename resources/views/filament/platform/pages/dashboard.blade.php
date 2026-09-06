<x-filament-panels::page class="fi-dashboard-page !p-0 !max-w-none">
    @php
        $alerts = $this->getOperationalAlerts();
        $dnsAlerts = $this->getDnsHealthAlerts();
        $actions = [...$alerts, ...$dnsAlerts];
        $hasDnsAlerts = count($dnsAlerts) > 0;
        $system = $this->getSystemHealth();
        $oldestAge = $system['queue']['oldest_pending_age_seconds'];
        $latestFailed = $system['queue']['recent_failed_jobs'][0] ?? null;
        $heartbeatAge = $system['scheduler']['age_seconds'];
        $recentActivity = $this->getRecentActivity();
        $kpis = app(\App\Services\PlatformDashboardService::class)->kpis();
    @endphp

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fragment+Mono&family=Instrument+Sans:wght@400;500;600;700&family=Newsreader:opsz,wght@6..72,400;6..72,600&display=swap');
        .dash-mono { font-family: 'Fragment Mono', ui-monospace, monospace; }
        .dash-display { font-family: 'Newsreader', 'Instrument Sans', serif; }
        .dash-sans { font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif; }
    </style>

    {{-- Hero — thesis: the platform as a living system --}}
    <div class="relative overflow-hidden border-b border-zinc-200/70 dark:border-zinc-800 bg-[#FCFCF9] dark:bg-[#0A0A0B]">
        {{-- Subtle grid texture --}}
        <div class="pointer-events-none absolute inset-0 opacity-[0.04] dark:opacity-[0.06]" style="background-image: linear-gradient(to right, #000 1px, transparent 1px), linear-gradient(to bottom, #000 1px, transparent 1px); background-size: 32px 32px;"></div>
        <div class="pointer-events-none absolute -top-24 right-[-8%] h-[420px] w-[520px] rounded-full bg-[#4F46E5]/[0.07] blur-[80px] dark:bg-[#4F46E5]/[0.12]"></div>
        <div class="pointer-events-none absolute -bottom-32 left-[-6%] h-[380px] w-[440px] rounded-full bg-[#EAB308]/[0.06] blur-[70px]"></div>

        <div class="relative max-w-[1600px] mx-auto px-6 sm:px-8 lg:px-10 py-8 sm:py-10">
            <div class="flex flex-wrap items-start justify-between gap-6">
                <div class="min-w-0">
                    <div class="inline-flex items-center gap-2 rounded-full border border-zinc-200 dark:border-zinc-800 bg-white/80 dark:bg-zinc-900/60 backdrop-blur px-3 py-1 text-xs">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <span class="dash-mono text-[11px] tracking-widest uppercase text-zinc-500 dark:text-zinc-400">Live — {{ config('app.env') }}</span>
                        <span class="h-3 w-px bg-zinc-200 dark:bg-zinc-800"></span>
                        <span class="text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ $kpis['total_tenants'] ?? 0 }} tenants</span>
                    </div>
                    <h1 class="dash-display mt-4 text-[28px] sm:text-[34px] font-semibold tracking-[-0.02em] leading-none text-zinc-900 dark:text-white">
                        Command <span class="font-light italic text-zinc-500 dark:text-zinc-400">center</span>
                    </h1>
                    <p class="dash-sans mt-2 max-w-[48ch] text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                        One screen for tenancy, billing and domain health. Act on what needs attention — everything else is already quiet.
                    </p>
                </div>

                {{-- KPI strip — editorial numbers --}}
                <div class="flex flex-wrap gap-3 sm:gap-4">
                    @php $kpiItems = [
                        ['label'=>'Active','value'=> $kpis['active_tenants'] ?? 0, 'sub'=>'of '.$kpis['total_tenants'].' total','tone'=>'ink'],
                        ['label'=>'Trial','value'=> $kpis['trial_tenants'] ?? 0, 'sub'=>'expiring '.$kpis['expiring_subscriptions'],'tone'=>'amber'],
                        ['label'=>'Domains','value'=> $kpis['active_domains'] ?? 0, 'sub'=>($kpis['dns_pending']??0).' pending','tone'=>'ink'],
                    ]; @endphp
                    @foreach($kpiItems as $kpi)
                        <div class="min-w-[110px] rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 shadow-[0_1px_2px_rgba(0,0,0,0.04)]">
                            <div class="dash-mono text-[11px] tracking-widest uppercase text-zinc-500">{{ $kpi['label'] }}</div>
                            <div class="mt-1 dash-display text-[28px] font-semibold leading-none tracking-tight text-zinc-900 dark:text-white">{{ $kpi['value'] }}</div>
                            <div class="mt-1 text-xs text-zinc-500">{{ $kpi['sub'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-[1600px] mx-auto px-6 sm:px-8 lg:px-10 py-6 sm:py-8 space-y-6">
        {{-- Action Required — bento, not generic 3-col --}}
        <section>
            <div class="flex items-baseline justify-between gap-4">
                <h2 class="dash-sans text-sm font-semibold tracking-tight text-zinc-900 dark:text-white flex items-center gap-2">
                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> Action required
                    @if(count($actions) > 0)
                        <span class="dash-mono ml-1 rounded-full bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 px-2 py-0.5 text-xs">{{ count($actions) }}</span>
                    @endif
                </h2>
                <span class="dash-mono text-xs text-zinc-500">{{ count($actions) ? count($actions).' items' : 'All clear' }}</span>
            </div>

            @if(count($actions) > 0)
                <div class="mt-3 grid grid-cols-1 lg:grid-cols-12 gap-4">
                    {{-- Primary alert (largest count) takes 7 cols --}}
                    @php $primary = collect($actions)->sortByDesc('count')->first(); $rest = collect($actions)->sortByDesc('count')->slice(1)->values(); @endphp
                    <div class="lg:col-span-7 rounded-2xl border border-zinc-900 dark:border-zinc-700 bg-zinc-900 dark:bg-zinc-900 text-white p-5 sm:p-6 relative overflow-hidden">
                        <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="dash-mono text-[11px] tracking-widest uppercase text-white/60">Most urgent</div>
                                <h3 class="mt-1 dash-display text-xl font-semibold leading-tight">{{ $primary['label'] }}</h3>
                                <p class="mt-1 text-sm leading-relaxed text-white/70 max-w-[42ch]">{{ $primary['description'] }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-white text-zinc-900 px-3 py-1 dash-mono text-sm font-semibold">{{ $primary['count'] }}</span>
                        </div>
                        @if(($primary['detail'] ?? null))
                            <div class="mt-3 inline-flex rounded-full bg-white/10 px-3 py-1 text-sm font-medium text-white">{{ $primary['detail'] }}</div>
                        @endif
                        <a href="{{ $primary['url'] }}" class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-white text-zinc-900 px-4 py-2 text-sm font-semibold hover:bg-zinc-50 transition">View {{ $primary['view_label'] ?? $primary['label'] }} <span aria-hidden="true">→</span></a>
                        @if(count($primary['domains'] ?? []) > 0)
                            <ul class="mt-4 space-y-2 max-h-[160px] overflow-auto pr-1">
                                @foreach(array_slice($primary['domains'],0,4) as $domain)
                                    <li class="flex items-center justify-between rounded-lg bg-white/5 px-3 py-2 text-xs">
                                        <span class="font-mono text-white">{{ $domain['domain'] }}</span>
                                        <span class="text-white/60">{{ $domain['tenant'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div class="lg:col-span-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-4">
                        @foreach($rest as $action)
                            <div class="rounded-2xl border {{ $action['tone']==='danger' ? 'border-rose-200 dark:border-rose-900/50 bg-rose-50/50 dark:bg-rose-950/20' : 'border-amber-200 dark:border-amber-900/50 bg-amber-50/60 dark:bg-amber-950/20' }} p-4 flex flex-col">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="text-sm font-semibold leading-tight text-zinc-900 dark:text-white">{{ $action['label'] }}</h3>
                                    <span class="shrink-0 rounded-full px-2 py-0.5 dash-mono text-xs font-bold {{ $action['tone']==='danger' ? 'bg-rose-600 text-white' : 'bg-amber-500 text-white' }}">{{ $action['count'] }}</span>
                                </div>
                                <p class="mt-1 text-xs leading-relaxed text-zinc-600 dark:text-zinc-400 line-clamp-2">{{ $action['description'] }}</p>
                                @if(($action['detail'] ?? null))
                                    <p class="mt-2 text-xs font-mono font-medium text-zinc-900 dark:text-white">{{ $action['detail'] }}</p>
                                @endif
                                <a href="{{ $action['url'] }}" class="mt-3 inline-flex w-fit items-center gap-1 text-xs font-semibold underline decoration-zinc-300 underline-offset-4 hover:decoration-zinc-900 dark:decoration-zinc-600 dark:hover:decoration-white">{{ $action['view_label'] ?? 'View' }} <span>→</span></a>
                            </div>
                        @endforeach
                        @if(!$hasDnsAlerts)
                            <div class="hidden lg:flex rounded-2xl border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50/40 dark:bg-emerald-950/10 p-4 items-center gap-3">
                                <span class="h-8 w-8 rounded-full bg-emerald-500 text-white grid place-items-center">✓</span>
                                <div><div class="text-sm font-semibold text-zinc-900 dark:text-white">Domains healthy</div><div class="text-xs text-zinc-600 dark:text-zinc-400">All verified, no action needed.</div></div>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="mt-3 rounded-2xl border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50/50 dark:bg-emerald-950/10 p-6 flex items-center gap-4">
                    <span class="h-10 w-10 rounded-full bg-emerald-500 text-white grid place-items-center text-lg">✓</span>
                    <div>
                        <div class="font-semibold text-zinc-900 dark:text-white">Everything is up to date.</div>
                        <div class="text-sm text-zinc-600 dark:text-zinc-400">No operational or DNS alerts — platform is quiet.</div>
                    </div>
                </div>
            @endif
        </section>

        {{-- System Health — editorial 5-up, not generic --}}
        <section>
            <div class="flex items-baseline justify-between">
                <h2 class="dash-sans text-sm font-semibold tracking-tight text-zinc-900 dark:text-white">System health</h2>
                <span class="dash-mono text-xs text-zinc-500">Last probe: just now</span>
            </div>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                @php
                    $healthCards = [
                        ['key'=>'Failed Jobs','value'=>$system['queue']['failed_jobs_count'],'note'=> $latestFailed ? Str::limit($latestFailed['exception'], 80) : 'No failed jobs','dot'=> $system['queue']['failed_jobs_count']>0 ? 'bg-rose-500' : 'bg-emerald-500'],
                        ['key'=>'Queue Backlog','value'=>$system['queue']['pending_jobs_count'],'note'=> $oldestAge!==null ? 'Oldest '.now()->subSeconds($oldestAge)->diffForHumans(['parts'=>1]).' ago' : 'Queue is clear','dot'=> $system['queue']['pending_jobs_count']>0 ? 'bg-amber-500' : 'bg-emerald-500'],
                        ['key'=>'Scheduler','value'=>ucfirst($system['scheduler']['status']),'note'=> $heartbeatAge!==null ? 'Last beat '.now()->subSeconds($heartbeatAge)->diffForHumans(['parts'=>1]).' ago' : 'No heartbeat yet','dot'=> $system['scheduler']['status']==='healthy' ? 'bg-emerald-500' : 'bg-rose-500'],
                        ['key'=>'Database','value'=>$system['app']['database'],'note'=> $system['app']['environment'].' environment','dot'=> $system['app']['database']==='OK' ? 'bg-emerald-500' : 'bg-rose-500'],
                        ['key'=>'Cache','value'=>$system['app']['cache'],'note'=> $system['app']['version'] ? 'App v'.$system['app']['version'] : 'Cache store responding','dot'=> $system['app']['cache']==='OK' ? 'bg-emerald-500' : 'bg-rose-500'],
                    ];
                @endphp
                @foreach($healthCards as $card)
                    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 flex flex-col">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full {{ $card['dot'] }}"></span>
                            <span class="dash-mono text-[11px] tracking-widest uppercase text-zinc-500">{{ $card['key'] }}</span>
                        </div>
                        <div class="mt-3 dash-display text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white leading-none">{{ $card['value'] }}</div>
                        <div class="mt-1 text-xs leading-snug text-zinc-500 dark:text-zinc-400 line-clamp-2">{{ $card['note'] }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Recent Activity — editorial table, not dense newspaper --}}
        <section>
            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden">
                <div class="flex items-center justify-between px-5 sm:px-6 py-4 border-b border-zinc-100 dark:border-zinc-800">
                    <h2 class="dash-sans text-sm font-semibold tracking-tight text-zinc-900 dark:text-white">Recent platform activity</h2>
                    <span class="dash-mono text-xs text-zinc-500">{{ count($recentActivity) }} events</span>
                </div>
                @if(count($recentActivity) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-zinc-50/80 dark:bg-zinc-900 border-b border-zinc-100 dark:border-zinc-800">
                                <tr class="dash-mono text-[11px] tracking-widest uppercase text-zinc-500">
                                    <th class="py-3 px-5 sm:px-6 font-medium text-left">Time</th>
                                    <th class="py-3 pr-4 font-medium">Type</th>
                                    <th class="py-3 pr-4 font-medium">Tenant</th>
                                    <th class="py-3 pr-4 font-medium">Action</th>
                                    <th class="py-3 pr-4 font-medium">Actor</th>
                                    <th class="py-3 pr-6 font-medium">Note</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach($recentActivity as $entry)
                                    <tr class="hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition">
                                        <td class="whitespace-nowrap py-3 px-5 sm:px-6 dash-mono text-xs text-zinc-500">{{ \Carbon\Carbon::parse($entry['time_label'])->format('M j, H:i') }}</td>
                                        <td class="py-3 pr-4"><span class="inline-flex rounded-full bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 px-2.5 py-1 dash-mono text-[11px] font-semibold tracking-wide uppercase">{{ $entry['badge'] }}</span></td>
                                        <td class="py-3 pr-4 font-medium text-zinc-900 dark:text-white max-w-[14ch] truncate">{{ $entry['tenant'] }}</td>
                                        <td class="py-3 pr-4 max-w-[28ch] truncate">
                                            @if($entry['url'])
                                                <a href="{{ $entry['url'] }}" class="underline decoration-zinc-300 dark:decoration-zinc-600 underline-offset-4 hover:decoration-zinc-900 dark:hover:decoration-white font-medium text-zinc-700 dark:text-zinc-200">{{ $entry['label'] }}</a>
                                            @else
                                                <span class="text-zinc-600 dark:text-zinc-300">{{ $entry['label'] }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3 pr-4 dash-mono text-xs text-zinc-500">{{ $entry['actor'] }}</td>
                                        <td class="py-3 pr-6 text-xs text-zinc-500 max-w-[22ch] truncate">{{ $entry['note'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-10 text-center">
                        <div class="mx-auto w-10 h-10 rounded-full bg-zinc-100 dark:bg-zinc-800 grid place-items-center text-zinc-400">◌</div>
                        <p class="mt-3 text-sm font-medium text-zinc-900 dark:text-white">No recent platform activity.</p>
                        <p class="text-xs text-zinc-500">Subscription and domain events will appear here.</p>
                    </div>
                @endif
            </div>
        </section>

        {{-- Quick Actions — bento, not generic 4-col --}}
        <section>
            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 sm:p-6">
                <div class="flex items-baseline justify-between">
                    <h2 class="dash-sans text-sm font-semibold tracking-tight text-zinc-900 dark:text-white">Quick Actions</h2>
                    <span class="dash-mono text-xs text-zinc-500">SaaS control</span>
                </div>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
                    @foreach($this->getQuickLinks() as $idx=>$link)
                        @php $span = $idx===0 ? 'lg:col-span-5' : ($idx===1 ? 'lg:col-span-7' : 'lg:col-span-3'); @endphp
                        <a href="{{ $link['url'] }}" class="{{ $span }} group flex items-center gap-4 rounded-2xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 hover:border-zinc-300 dark:hover:border-zinc-700 hover:bg-zinc-50/60 dark:hover:bg-zinc-800/60 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                            <span class="h-10 w-10 rounded-xl bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 grid place-items-center group-hover:scale-[1.02] transition">
                                <x-filament::icon :icon="$link['icon']" class="h-5 w-5" />
                            </span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-semibold text-zinc-900 dark:text-white">{{ $link['label'] }}</span>
                                <span class="block dash-mono text-xs text-zinc-500">Open →</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
