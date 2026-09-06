@extends('storefront.layout')

@section('title', 'Order ' . $order->order_number . ' - ' . tenant()->name)

@section('content')
    @include('storefront.partials.seo-meta', ['robots' => 'noindex,nofollow'])

    @php
        $statusColors = [
            'pending' => 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800',
            'confirmed' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800',
            'processing' => 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border-blue-200',
            'shipped' => 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 border-violet-200',
            'delivered' => 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border-emerald-200',
            'cancelled' => 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-200',
            'canceled' => 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-200',
            'refunded' => 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-200',
            'failed' => 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 border-red-200',
        ];
        $statusKey = strtolower($order->status->value ?? $order->status);
        $statusClass = $statusColors[$statusKey] ?? 'bg-gray-100 dark:bg-gray-800 text-gray-700 border-gray-200';
        $placedAt = $order->placed_at ?? $order->created_at;
    @endphp

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 pb-24 lg:pb-10">
        {{-- Back + header --}}
        <div class="flex items-center justify-between gap-4 mb-6">
            <a href="{{ route('storefront.track-order.form') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                Track another order
            </a>
            <button type="button" onclick="if(navigator.share){navigator.share({title: document.title, url: location.href})}else{navigator.clipboard.writeText(location.href); window.dispatchEvent(new CustomEvent('toast',{detail:{message:'Link copied',type:'success'}}))}" class="hidden sm:inline-flex items-center gap-1.5 rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-300 hover:border-gray-300">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z"/></svg>
                Share
            </button>
        </div>

        {{-- Order header card --}}
        <div class="relative overflow-hidden rounded-3xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-soft">
            <div class="absolute inset-0 bg-gradient-to-br from-gray-50 via-transparent to-transparent dark:from-gray-800/50 pointer-events-none"></div>
            <div class="absolute -top-16 -right-16 w-40 h-40 rounded-full bg-[var(--brand)]/[0.04] blur-2xl pointer-events-none"></div>
            <div class="relative p-6 sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold tracking-widest uppercase text-gray-400 dark:text-gray-500">Order</p>
                        <h1 class="mt-1 font-mono text-xl sm:text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $order->order_number }}</h1>
                        <p class="mt-1.5 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <span class="inline-flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75M3 12h18"/></svg> Placed {{ $placedAt?->format('M j, Y \a\t g:i A') ?? $order->created_at->format('M j, Y') }}</span>
                            <span class="hidden sm:inline">•</span>
                            <span>{{ $order->items->count() }} {{ Str::plural('item', $order->items->count()) }}</span>
                        </p>
                    </div>
                    <span class="inline-flex items-center rounded-full border px-3 py-1.5 text-xs font-bold tracking-wide uppercase {{ $statusClass }}">{{ $order->status->label() }}</span>
                </div>

                <div class="mt-6 grid grid-cols-3 gap-3">
                    <div class="rounded-2xl bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-800 p-3 sm:p-4">
                        <p class="text-xs font-semibold tracking-wide uppercase text-gray-400">Total</p>
                        <p class="mt-1 text-sm sm:text-base font-bold text-gray-900 dark:text-white">{{ money((int) $order->grand_total) }}</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-800 p-3 sm:p-4">
                        <p class="text-xs font-semibold tracking-wide uppercase text-gray-400">Payment</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $order->payment_method_label ?? $order->payment_status?->label() ?? '—' }}</p>
                    </div>
                    <div class="rounded-2xl bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-800 p-3 sm:p-4">
                        <p class="text-xs font-semibold tracking-wide uppercase text-gray-400">Fulfillment</p>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $order->fulfillments->isNotEmpty() ? $order->fulfillments->count().' '.Str::plural('shipment', $order->fulfillments->count()) : 'Processing' }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Items --}}
        <div class="mt-6 bg-white dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 shadow-soft overflow-hidden">
            <div class="px-6 sm:px-8 pt-6 pb-3 flex items-center justify-between">
                <h2 class="text-sm font-bold tracking-wide uppercase text-gray-900 dark:text-white">Items</h2>
                <span class="text-xs font-medium text-gray-500 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-full px-2.5 py-1">{{ $order->items->count() }} items • {{ money((int) $order->grand_total) }}</span>
            </div>
            <div class="divide-y divide-gray-50 dark:divide-gray-800/60">
                @foreach ($order->items as $item)
                    <div class="px-6 sm:px-8 py-4 flex gap-4">
                        <div class="flex-shrink-0 w-14 h-14 rounded-xl bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 flex items-center justify-center overflow-hidden">
                            <span class="text-xs font-bold text-gray-400">×{{ $item->quantity }}</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold leading-snug text-gray-900 dark:text-white truncate">{{ $item->product_name_snapshot }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Qty {{ $item->quantity }} • {{ money((int) $item->unit_price) }} each</p>
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                @if (($item->fulfillment_strategy ?? null) === 'preorder')
                                    <span class="inline-flex items-center rounded-full bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300 border border-violet-200 dark:border-violet-800 px-2 py-0.5 text-xs font-semibold">PRE-ORDER</span>
                                @endif
                                @if (($item->fulfillment_strategy ?? null) === 'preorder' && $item->expected_available_at)
                                    <span class="inline-flex items-center rounded-full bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 px-2 py-0.5 text-xs">Expected {{ $item->expected_available_at->format('M j, Y') }}</span>
                                @endif
                            </div>
                        </div>
                        <span class="flex-shrink-0 text-sm font-bold text-gray-900 dark:text-white">{{ money((int) $item->line_total) }}</span>
                    </div>
                @endforeach
            </div>
            <div class="bg-gray-50/70 dark:bg-gray-800/30 px-6 sm:px-8 py-4 flex items-center justify-between border-t border-gray-100 dark:border-gray-800">
                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Grand total</span>
                <span class="text-base font-bold text-gray-900 dark:text-white">{{ money((int) $order->grand_total) }}</span>
            </div>
        </div>

        {{-- Fulfillments --}}
        @if ($order->fulfillments->isNotEmpty())
            <div class="mt-6 bg-white dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 shadow-soft overflow-hidden">
                <div class="px-6 sm:px-8 pt-6 pb-3 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-xl bg-[var(--brand)]/10 flex items-center justify-center text-[var(--brand)]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                    </span>
                    <h2 class="text-sm font-bold tracking-wide uppercase text-gray-900 dark:text-white">Shipments</h2>
                    <span class="ml-auto text-xs font-medium text-gray-500 bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-full px-2.5 py-1">{{ $order->fulfillments->count() }}</span>
                </div>
                <div class="divide-y divide-gray-50 dark:divide-gray-800/60">
                    @foreach ($order->fulfillments as $fulfillment)
                        <div class="px-6 sm:px-8 py-5">
                            <div class="flex flex-wrap items-center gap-2 mb-3">
                                <span class="inline-flex items-center rounded-full bg-gray-900 dark:bg-white px-2.5 py-1 text-xs font-bold tracking-wide uppercase text-white dark:text-gray-900">{{ $fulfillment->fulfillment_group ?? 'stock' }}</span>
                                <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold
                                    @if (($fulfillment->status->value ?? $fulfillment->status) === 'delivered') bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-300 border-emerald-200 @elseif(in_array($fulfillment->status->value ?? $fulfillment->status, ['shipped','packed'])) bg-blue-50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-300 border-blue-200 @else bg-amber-50 dark:bg-amber-950/30 text-amber-700 dark:text-amber-300 border-amber-200 @endif
                                ">{{ $fulfillment->status->label() }}</span>
                            </div>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                @if ($fulfillment->expected_available_at)
                                    <div class="flex gap-2"><dt class="text-gray-400 w-24 shrink-0">ETA</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $fulfillment->expected_available_at->format('M j, Y') }}</dd></div>
                                @endif
                                @if ($fulfillment->courier_name)
                                    <div class="flex gap-2"><dt class="text-gray-400 w-24 shrink-0">Courier</dt><dd class="font-medium text-gray-900 dark:text-white">{{ $fulfillment->courier_name }}</dd></div>
                                @endif
                                @if ($fulfillment->tracking_number)
                                    <div class="flex gap-2 sm:col-span-2"><dt class="text-gray-400 w-24 shrink-0">Tracking</dt>
                                        <dd class="flex items-center gap-2 min-w-0">
                                            <span class="font-mono text-sm font-semibold text-gray-900 dark:text-white tracking-wide truncate">{{ $fulfillment->tracking_number }}</span>
                                            <button type="button" onclick="navigator.clipboard.writeText('{{ $fulfillment->tracking_number }}'); window.dispatchEvent(new CustomEvent('toast',{detail:{message:'Tracking number copied',type:'success'}}))" class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-500 hover:text-gray-900 dark:hover:text-white hover:border-gray-300 transition flex-shrink-0" aria-label="Copy tracking number">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 01-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a.375.375 0 01.375.375v11.25c0 .621.504 1.125 1.125 1.125h9.75a.375.375 0 01.375.375zM12.75 7.5a.375.375 0 01.375-.375h7.5a1.125 1.125 0 011.125 1.125v9.75a1.125 1.125 0 01-1.125 1.125h-7.5a1.125 1.125 0 01-1.125-1.125V7.875a1.125 1.125 0 011.125-1.125h1.5a.375.375 0 01.375.375v1.5z"/></svg>
                                            </button>
                                        </dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Timeline --}}
        <div class="mt-6 bg-white dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 shadow-soft overflow-hidden">
            <div class="px-6 sm:px-8 pt-6 pb-2 flex items-center gap-2">
                <span class="w-7 h-7 rounded-xl bg-gray-900 dark:bg-white flex items-center justify-center text-white dark:text-gray-900">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <h2 class="text-sm font-bold tracking-wide uppercase text-gray-900 dark:text-white">Timeline</h2>
                <span class="ml-auto text-xs text-gray-400">{{ $order->events->count() }} updates</span>
            </div>
            <div class="px-6 sm:px-8 pb-6">
                @if ($order->events->isEmpty())
                    <p class="text-sm text-gray-400 py-4">No updates yet — we’ll post here as your order moves.</p>
                @else
                    <ol class="relative border-l border-gray-100 dark:border-gray-800 ml-3 pl-6 space-y-0">
                        @foreach ($order->events as $idx => $event)
                            <li class="relative py-4 @if (!$loop->last) border-b border-gray-50 dark:border-gray-800/50 @endif">
                                <span class="absolute -left-[25px] top-5 w-3 h-3 rounded-full border-2 bg-white dark:bg-gray-900
                                    @if ($idx===0) border-emerald-500 bg-emerald-500 @else border-gray-200 dark:border-gray-700 @endif
                                    flex items-center justify-center">
                                    @if ($idx===0)
                                        <span class="w-1.5 h-1.5 rounded-full bg-white"></span>
                                    @endif
                                </span>
                                <p class="text-sm font-medium leading-snug text-gray-900 dark:text-white">{{ $event->description }}</p>
                                <p class="mt-1 flex items-center gap-1.5 text-xs text-gray-400">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    {{ $event->created_at->diffForHumans() }}
                                    <span class="text-gray-300">•</span>
                                    <span class="font-mono text-xs">{{ $event->created_at->format('M j, g:i A') }}</span>
                                </p>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </div>

        {{-- Actions --}}
        <div class="mt-6 flex flex-col sm:flex-row gap-3">
            <a href="{{ route('storefront.home') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 px-5 py-3 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:border-gray-300 dark:hover:border-gray-600 transition flex-1 sm:flex-none">
                Continue shopping
            </a>
            <a href="mailto:{{ tenant()->contact_email ?? '' }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[var(--brand)] px-5 py-3 text-sm font-semibold text-white shadow-sm hover:brightness-[0.98] active:brightness-95 flex-1 sm:flex-none">
                Need help?
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91A2.25 2.25 0 012.25 7.014V6.75"/></svg>
            </a>
        </div>

        <p class="mt-6 text-center text-xs text-gray-400 dark:text-gray-500">
            Questions? Contact <a href="mailto:{{ tenant()->contact_email ?? '' }}" class="font-medium text-[var(--brand)] hover:underline underline-offset-2">{{ tenant()->contact_email ?? 'support' }}</a>
            @if (tenant()->contact_phone) • <a href="tel:{{ tenant()->contact_phone }}" class="font-medium text-[var(--brand)] hover:underline underline-offset-2">{{ tenant()->contact_phone }}</a> @endif
        </p>
    </div>
@endsection
