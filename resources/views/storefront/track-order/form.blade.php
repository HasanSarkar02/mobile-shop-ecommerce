@extends('storefront.layout')

@section('title', 'Track Your Order - ' . tenant()->name)

@section('content')
    @include('storefront.partials.seo-meta', ['robots' => 'noindex,nofollow'])

    <div class="min-h-[calc(100vh-280px)] flex items-center py-8 sm:py-12">
        <div class="w-full max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-[1.1fr_0.9fr] gap-6 lg:gap-8 items-center">
                {{-- Left: context --}}
                <div class="order-2 lg:order-1">
                    <div class="inline-flex items-center gap-2 rounded-full border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-3 py-1 text-xs font-medium text-gray-600 dark:text-gray-300">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Live tracking — updated hourly
                    </div>
                    <h1 class="mt-4 text-3xl sm:text-4xl font-bold tracking-tight text-gray-900 dark:text-white leading-[0.95]">
                        Track your<br>
                        <span class="font-light text-gray-500 dark:text-gray-400">order in seconds.</span>
                    </h1>
                    <p class="mt-3 text-sm leading-relaxed text-gray-500 dark:text-gray-400 max-w-md">
                        Enter the order number from your confirmation email and the email you used at checkout. We’ll show the latest status, fulfillment and a full timeline.
                    </p>

                    <div class="mt-8 hidden lg:block">
                        <div class="relative rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 p-5">
                            <div class="absolute -top-3 left-6 rounded-full bg-gray-900 dark:bg-white px-2.5 py-1 text-xs font-semibold text-white dark:text-gray-900">How it works</div>
                            <ol class="space-y-4 pt-2">
                                <li class="flex gap-3">
                                    <span class="flex-shrink-0 w-7 h-7 rounded-full bg-[var(--brand)] text-white flex items-center justify-center text-xs font-bold">1</span>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Order number</p>
                                        <p class="text-xs text-gray-500">e.g. <span class="font-mono">ORD-2026-000123</span> — from your email</p>
                                    </div>
                                </li>
                                <li class="flex gap-3">
                                    <span class="flex-shrink-0 w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-xs font-bold text-gray-600 dark:text-gray-300">2</span>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Email address</p>
                                        <p class="text-xs text-gray-500">The same email you used to place the order</p>
                                    </div>
                                </li>
                                <li class="flex gap-3">
                                    <span class="flex-shrink-0 w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-xs font-bold text-gray-600 dark:text-gray-300">3</span>
                                    <div>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white">Instant status</p>
                                        <p class="text-xs text-gray-500">See fulfillment, courier and every update</p>
                                    </div>
                                </li>
                            </ol>
                        </div>
                        <p class="mt-4 flex items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 00-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                            Your email is only used to verify ownership
                        </p>
                    </div>
                </div>

                {{-- Right: form card --}}
                <div class="order-1 lg:order-2">
                    <div class="relative bg-white dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 shadow-soft p-6 sm:p-8 overflow-hidden">
                        <div class="absolute -top-20 -right-20 w-48 h-48 rounded-full bg-[var(--brand)]/[0.06] blur-2xl pointer-events-none"></div>

                        <div class="relative">
                            <div class="flex items-center justify-between gap-4">
                                <h2 class="text-lg font-bold tracking-tight text-gray-900 dark:text-white">Track Your Order</h2>
                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-50 dark:bg-gray-800 border border-gray-100 dark:border-gray-700 px-2.5 py-1 text-xs font-medium text-gray-600 dark:text-gray-300">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Secure
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">We’ll pull the latest updates from our courier partners.</p>

                            @if ($errors->any())
                                <div class="mt-5 rounded-xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/40 px-3 py-3 flex gap-2.5">
                                    <svg class="w-4 h-4 text-red-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                                    <p class="text-sm text-red-700 dark:text-red-300">{{ $errors->first() }}</p>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('storefront.track-order.show') }}" class="mt-6 space-y-4">
                                @csrf
                                <div class="space-y-1.5">
                                    <label for="order_number" class="text-xs font-semibold tracking-wide uppercase text-gray-600 dark:text-gray-300">Order number</label>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-3.375c0-.621-.504-1.125-1.125-1.125h-1.5a1.5 1.5 0 01-1.5-1.5v-1.5A1.5 1.5 0 0014.25 6H9.75A1.5 1.5 0 008.25 7.5v1.5A1.5 1.5 0 016.75 10.5h-1.5A1.125 1.125 0 004.125 11.625v3.375c0 .621.504 1.125 1.125 1.125h1.5a1.5 1.5 0 011.5 1.5v1.5c0 .621.504 1.125 1.125 1.125h5.25A1.125 1.125 0 0015 16.5v-1.5a1.5 1.5 0 011.5-1.5h1.5c.621 0 1.125-.504 1.125-1.125z"/></svg>
                                        </span>
                                        <input id="order_number" name="order_number" value="{{ old('order_number') }}" placeholder="ORD-2026-000123" autocomplete="off" required
                                            class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 pl-10 pr-3 py-3 font-mono text-sm tracking-wide placeholder:text-gray-400 placeholder:font-sans placeholder:tracking-normal focus:bg-white dark:focus:bg-gray-900 focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 outline-none transition">
                                    </div>
                                    <p class="text-xs text-gray-400 dark:text-gray-500">Tip: copy from your “Order Confirmed” email subject.</p>
                                </div>

                                <div class="space-y-1.5">
                                    <label for="email" class="text-xs font-semibold tracking-wide uppercase text-gray-600 dark:text-gray-300">Email used at checkout</label>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91A2.25 2.25 0 012.25 7.014V6.75"/></svg>
                                        </span>
                                        <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email" required
                                            class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 pl-10 pr-3 py-3 text-sm placeholder:text-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 outline-none transition">
                                    </div>
                                </div>

                                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[var(--brand)] px-4 py-3.5 text-sm font-semibold text-white shadow-sm shadow-black/5 hover:brightness-[0.98] active:brightness-95 focus:outline-none focus-visible:ring-4 focus-visible:ring-[var(--brand)]/20 transition">
                                    Track Order
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                                </button>

                                <p class="text-center text-xs text-gray-400 dark:text-gray-500">
                                    Need help? <a href="mailto:{{ tenant()->contact_email ?? '' }}" class="font-medium text-[var(--brand)] hover:underline underline-offset-2">Contact support</a>
                                    @if (tenant()->contact_phone)
                                        <span>•</span> <a href="tel:{{ tenant()->contact_phone }}" class="font-medium text-[var(--brand)] hover:underline underline-offset-2">{{ tenant()->contact_phone }}</a>
                                    @endif
                                </p>
                            </form>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-center gap-3 text-xs text-gray-400 dark:text-gray-500">
                        <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Encrypted</span>
                        <span>•</span>
                        <span>Privacy-first — we don’t store this lookup</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
