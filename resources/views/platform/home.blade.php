@extends('platform.layout')

@section('title', 'MobileShop BD — Launch Your Mobile Shop Online in Minutes')

@section('content')
    <div class="min-h-screen" style="--brand: #059669">
        {{-- ============ NAV ============ --}}
        <header class="fixed inset-x-0 top-0 z-50 border-b border-gray-200/70 bg-white/80 backdrop-blur-lg dark:border-gray-800 dark:bg-gray-950/80">
            <nav class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <a href="#top" class="flex items-center gap-2">
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-[var(--brand)] text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </span>
                    <span class="text-lg font-bold tracking-tight text-gray-900 dark:text-white">MobileShop<span
                            class="text-[var(--brand)]">BD</span></span>
                </a>

                <div class="hidden items-center gap-8 text-sm font-medium text-gray-600 dark:text-gray-300 lg:flex">
                    <a href="#features" class="hover:text-[var(--brand)]">Features</a>
                    <a href="#how-it-works" class="hover:text-[var(--brand)]">How it works</a>
                    <a href="#payments" class="hover:text-[var(--brand)]">Payments</a>
                    <a href="#pricing" class="hover:text-[var(--brand)]">Pricing</a>
                    <a href="#faq" class="hover:text-[var(--brand)]">FAQ</a>
                </div>

                <div class="flex items-center gap-3">
                    <x-ui.button as="a" href="{{ route('platform.signup') }}" size="md">
                        Start Free Trial
                    </x-ui.button>
                    <button type="button" @click="open = !open" x-data="{ open: false }"
                        class="grid h-10 w-10 place-items-center rounded-xl border border-gray-200 text-gray-700 dark:border-gray-700 dark:text-gray-200 lg:hidden">
                        <svg x-show="!open" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg x-show="open" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </nav>

            {{-- Mobile menu --}}
            <div x-data="{ open: false }" class="lg:hidden">
                <div x-show="open" x-cloak class="border-t border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-950">
                    <div class="flex flex-col gap-4 text-sm font-medium text-gray-700 dark:text-gray-200">
                        <a href="#features" @click="open = false">Features</a>
                        <a href="#how-it-works" @click="open = false">How it works</a>
                        <a href="#payments" @click="open = false">Payments</a>
                        <a href="#pricing" @click="open = false">Pricing</a>
                        <a href="#faq" @click="open = false">FAQ</a>
                    </div>
                </div>
            </div>
        </header>

        {{-- ============ HERO ============ --}}
        <section id="top" class="relative overflow-hidden pt-32 pb-20 sm:pt-40 sm:pb-28">
            <div class="pointer-events-none absolute inset-0 -z-10"
                style="background: radial-gradient(60% 50% at 50% 0%, rgb(5 150 105 / 0.14), transparent 70%);">
            </div>
            <div class="pointer-events-none absolute inset-0 -z-10 opacity-[0.35]"
                style="background-image: radial-gradient(rgb(5 150 105 / 0.18) 1px, transparent 1px); background-size: 28px 28px;">
            </div>

            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-3xl text-center">
                    <span
                        class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-1.5 text-xs font-semibold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        Made for mobile shops in Bangladesh
                    </span>

                    <h1 class="mt-6 text-4xl font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-5xl lg:text-6xl">
                        Your mobile shop.
                        <span class="bg-gradient-to-r from-emerald-600 to-teal-500 bg-clip-text text-transparent">Online in
                            minutes.</span>
                    </h1>

                    <p class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                        Sell phones, accessories, and gadgets to customers across Bangladesh — 24/7. Manage your catalog,
                        orders, bKash &amp; Nagad payments, and stock from one simple dashboard. No tech skills needed.
                    </p>

                    <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <x-ui.button as="a" href="{{ route('platform.signup') }}" size="lg">
                            Start Free 14-Day Trial
                        </x-ui.button>
                        <a href="#how-it-works"
                            class="inline-flex items-center gap-2 rounded-xl px-6 py-3 text-base font-medium text-gray-700 dark:text-gray-200 hover:text-[var(--brand)]">
                            See how it works
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                            </svg>
                        </a>
                    </div>

                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No credit card required. Cancel anytime.</p>
                </div>

                {{-- Hero mockup --}}
                <div class="mx-auto mt-16 max-w-5xl">
                    <div class="rounded-2xl border border-gray-200 bg-white p-2 shadow-elevated dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex items-center gap-1.5 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                            <span class="h-3 w-3 rounded-full bg-red-400"></span>
                            <span class="h-3 w-3 rounded-full bg-amber-400"></span>
                            <span class="h-3 w-3 rounded-full bg-emerald-400"></span>
                            <span class="ml-3 flex-1 rounded-lg bg-gray-100 px-3 py-1 text-xs text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                yourshop.mobileshopbd.com
                            </span>
                        </div>
                        <div class="grid gap-4 p-4 sm:grid-cols-3">
                            <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Today's Sales</p>
                                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">৳ 86,500</p>
                                <p class="mt-1 text-xs font-medium text-emerald-600">+24% from yesterday</p>
                            </div>
                            <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Pending Orders</p>
                                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">12</p>
                                <p class="mt-1 text-xs font-medium text-amber-600">6 need delivery</p>
                            </div>
                            <div class="rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Products in Stock</p>
                                <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-white">248</p>
                                <p class="mt-1 text-xs font-medium text-emerald-600">All healthy</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Social proof --}}
                <div class="mx-auto mt-16 max-w-4xl">
                    <p class="text-center text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                        Trusted by mobile shops across Bangladesh
                    </p>
                    <div class="mt-6 flex flex-wrap items-center justify-center gap-x-10 gap-y-4 text-center">
                        <span class="text-sm font-bold text-gray-500 dark:text-gray-400">TechBazaar Dhaka</span>
                        <span class="text-sm font-bold text-gray-500 dark:text-gray-400">Mobile House Ctg</span>
                        <span class="text-sm font-bold text-gray-500 dark:text-gray-400">Gadget Hub Sylhet</span>
                        <span class="text-sm font-bold text-gray-500 dark:text-gray-400">Phone Mart Khulna</span>
                        <span class="text-sm font-bold text-gray-500 dark:text-gray-400">iZone Rajshahi</span>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ STATS ============ --}}
        <section class="border-y border-gray-200 bg-white py-14 dark:border-gray-800 dark:bg-gray-900">
            <div class="mx-auto grid max-w-7xl grid-cols-2 gap-8 px-4 text-center sm:px-6 lg:grid-cols-4 lg:px-8">
                <div>
                    <p class="text-3xl font-extrabold text-gray-900 dark:text-white">2,500+</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Active shops</p>
                </div>
                <div>
                    <p class="text-3xl font-extrabold text-gray-900 dark:text-white">৳ 12 Cr+</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Monthly GMV processed</p>
                </div>
                <div>
                    <p class="text-3xl font-extrabold text-gray-900 dark:text-white">64</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Districts covered</p>
                </div>
                <div>
                    <p class="text-3xl font-extrabold text-gray-900 dark:text-white">4.8/5</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Shop owner rating</p>
                </div>
            </div>
        </section>

        {{-- ============ FEATURES ============ --}}
        <section id="features" class="py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <span class="text-sm font-semibold text-[var(--brand)]">Everything you need</span>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                        Run your whole shop from one dashboard
                    </h2>
                    <p class="mt-4 text-lg text-gray-600 dark:text-gray-300">
                        Stop juggling paper notes, phone calls, and multiple apps. MobileShopBD brings every part of your
                        business together.
                    </p>
                </div>

                <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {{-- Product catalog --}}
                    <div class="group rounded-2xl border border-gray-200 bg-white p-7 transition hover:border-emerald-300 hover:shadow-elevated dark:border-gray-800 dark:bg-gray-900 dark:hover:border-emerald-800">
                        <div class="grid h-12 w-12 place-items-center rounded-xl bg-emerald-50 text-[var(--brand)] dark:bg-emerald-950">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <h3 class="mt-5 text-lg font-bold text-gray-900 dark:text-white">Beautiful product catalog</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                            Add phones, accessories, and parts with photos, variants, and prices. Your shop gets a
                            professional storefront customers love to browse.
                        </p>
                    </div>

                    {{-- Orders --}}
                    <div class="group rounded-2xl border border-gray-200 bg-white p-7 transition hover:border-emerald-300 hover:shadow-elevated dark:border-gray-800 dark:bg-gray-900 dark:hover:border-emerald-800">
                        <div class="grid h-12 w-12 place-items-center rounded-xl bg-emerald-50 text-[var(--brand)] dark:bg-emerald-950">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                            </svg>
                        </div>
                        <h3 class="mt-5 text-lg font-bold text-gray-900 dark:text-white">Smart order management</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                            Track every order from "placed" to "delivered". Get notified instantly, print invoices, and
                            never lose track of a sale again.
                        </p>
                    </div>

                    {{-- Payments --}}
                    <div class="group rounded-2xl border border-gray-200 bg-white p-7 transition hover:border-emerald-300 hover:shadow-elevated dark:border-gray-800 dark:bg-gray-900 dark:hover:border-emerald-800">
                        <div class="grid h-12 w-12 place-items-center rounded-xl bg-emerald-50 text-[var(--brand)] dark:bg-emerald-950">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 10h18M7 15h2m4 0h4M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h3 class="mt-5 text-lg font-bold text-gray-900 dark:text-white">bKash, Nagad &amp; more</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                            Accept bKash, Nagad, Rocket, cards, and cash on delivery. Payments show up in your dashboard
                            automatically — no manual ledger.
                        </p>
                    </div>

                    {{-- Inventory --}}
                    <div class="group rounded-2xl border border-gray-200 bg-white p-7 transition hover:border-emerald-300 hover:shadow-elevated dark:border-gray-800 dark:bg-gray-900 dark:hover:border-emerald-800">
                        <div class="grid h-12 w-12 place-items-center rounded-xl bg-emerald-50 text-[var(--brand)] dark:bg-emerald-950">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 6h18M3 12h18M3 18h18" />
                            </svg>
                        </div>
                        <h3 class="mt-5 text-lg font-bold text-gray-900 dark:text-white">Live inventory &amp; serial numbers</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                            Track stock in real time, manage IMEI/serial numbers for every handset, and get low-stock
                            alerts before you run out.
                        </p>
                    </div>

                    {{-- Marketing --}}
                    <div class="group rounded-2xl border border-gray-200 bg-white p-7 transition hover:border-emerald-300 hover:shadow-elevated dark:border-gray-800 dark:bg-gray-900 dark:hover:border-emerald-800">
                        <div class="grid h-12 w-12 place-items-center rounded-xl bg-emerald-50 text-[var(--brand)] dark:bg-emerald-950">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </div>
                        <h3 class="mt-5 text-lg font-bold text-gray-900 dark:text-white">Built-in marketing tools</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                            Create coupons, run campaigns, and share your shop link on Facebook &amp; WhatsApp so customers
                            can order in a few taps.
                        </p>
                    </div>

                    {{-- Analytics --}}
                    <div class="group rounded-2xl border border-gray-200 bg-white p-7 transition hover:border-emerald-300 hover:shadow-elevated dark:border-gray-800 dark:bg-gray-900 dark:hover:border-emerald-800">
                        <div class="grid h-12 w-12 place-items-center rounded-xl bg-emerald-50 text-[var(--brand)] dark:bg-emerald-950">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <h3 class="mt-5 text-lg font-bold text-gray-900 dark:text-white">Clear sales analytics</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                            See daily sales, top-selling models, and profit in simple charts. Make decisions based on data,
                            not guesswork.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ HOW IT WORKS ============ --}}
        <section id="how-it-works" class="bg-gray-50 py-24 dark:bg-gray-900">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <span class="text-sm font-semibold text-[var(--brand)]">Simple to start</span>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                        Live in 3 easy steps
                    </h2>
                    <p class="mt-4 text-lg text-gray-600 dark:text-gray-300">
                        You don't need a developer. If you can use Facebook, you can run a MobileShopBD store.
                    </p>
                </div>

                <div class="mt-16 grid gap-8 md:grid-cols-3">
                    <div class="relative rounded-2xl border border-gray-200 bg-white p-8 dark:border-gray-800 dark:bg-gray-950">
                        <span
                            class="grid h-10 w-10 place-items-center rounded-full bg-[var(--brand)] text-lg font-bold text-white">1</span>
                        <h3 class="mt-5 text-lg font-bold text-gray-900 dark:text-white">Create your shop</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                            Sign up, pick your shop name, and choose your free subdomain — like
                            <span class="font-semibold text-gray-900 dark:text-white">yourshop.mobileshopbd.com</span>.
                            Takes 2 minutes.
                        </p>
                    </div>
                    <div class="relative rounded-2xl border border-gray-200 bg-white p-8 dark:border-gray-800 dark:bg-gray-950">
                        <span
                            class="grid h-10 w-10 place-items-center rounded-full bg-[var(--brand)] text-lg font-bold text-white">2</span>
                        <h3 class="mt-5 text-lg font-bold text-gray-900 dark:text-white">Add your products</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                            Upload phone photos and prices, or let our team import your existing catalog for free. Add
                            bKash &amp; Nagad numbers for payments.
                        </p>
                    </div>
                    <div class="relative rounded-2xl border border-gray-200 bg-white p-8 dark:border-gray-800 dark:bg-gray-950">
                        <span
                            class="grid h-10 w-10 place-items-center rounded-full bg-[var(--brand)] text-lg font-bold text-white">3</span>
                        <h3 class="mt-5 text-lg font-bold text-gray-900 dark:text-white">Start selling</h3>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                            Share your link on Facebook and WhatsApp. Customers browse, order, and pay — you pack and
                            deliver. That's it.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ PAYMENTS ============ --}}
        <section id="payments" class="py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid items-center gap-12 lg:grid-cols-2">
                    <div>
                        <span class="text-sm font-semibold text-[var(--brand)]">Payments</span>
                        <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                            Get paid the way Bangladeshis pay
                        </h2>
                        <p class="mt-4 text-lg leading-relaxed text-gray-600 dark:text-gray-300">
                            No confusing international gateways. Accept every payment method your customers already trust —
                            all in one checkout, all tracked in one dashboard.
                        </p>
                        <ul class="mt-8 space-y-4">
                            <li class="flex items-start gap-3">
                                <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    <span class="font-semibold text-gray-900 dark:text-white">Automatic payment matching</span>
                                    — when a customer pays via bKash, the transaction shows up against their order instantly.
                                </p>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    <span class="font-semibold text-gray-900 dark:text-white">Cash on delivery</span>
                                    for your local customers who prefer to pay in hand.
                                </p>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    <span class="font-semibold text-gray-900 dark:text-white">Reconciliation reports</span>
                                    so you always know what's paid and what's still outstanding.
                                </p>
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-8 dark:border-gray-800 dark:bg-gray-900">
                        <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">
                            Supported payment methods
                        </p>
                        <div class="mt-6 grid grid-cols-2 gap-4">
                            <div class="rounded-xl border border-gray-200 bg-white p-5 text-center shadow-soft dark:border-gray-700 dark:bg-gray-950">
                                <span class="text-xl font-extrabold text-pink-600">bKash</span>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Personal &amp; merchant</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 bg-white p-5 text-center shadow-soft dark:border-gray-700 dark:bg-gray-950">
                                <span class="text-xl font-extrabold text-orange-500">Nagad</span>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Send money &amp; merchant</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 bg-white p-5 text-center shadow-soft dark:border-gray-700 dark:bg-gray-950">
                                <span class="text-xl font-extrabold text-purple-600">Rocket</span>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">DBBL mobile banking</p>
                            </div>
                            <div class="rounded-xl border border-gray-200 bg-white p-5 text-center shadow-soft dark:border-gray-700 dark:bg-gray-950">
                                <span class="text-xl font-extrabold text-emerald-600">Cards</span>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Visa &amp; Mastercard</p>
                            </div>
                            <div class="col-span-2 rounded-xl border border-gray-200 bg-white p-5 text-center shadow-soft dark:border-gray-700 dark:bg-gray-950">
                                <span class="text-xl font-extrabold text-gray-800 dark:text-white">Cash on Delivery</span>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">The way local customers love to shop</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============ TESTIMONIALS ============ --}}
        <section class="bg-gray-50 py-24 dark:bg-gray-900">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <span class="text-sm font-semibold text-[var(--brand)]">Loved by shop owners</span>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                        Real shops, real results
                    </h2>
                </div>

                <div class="mt-16 grid gap-6 md:grid-cols-3">
                    <figure class="rounded-2xl border border-gray-200 bg-white p-7 shadow-soft dark:border-gray-800 dark:bg-gray-950">
                        <div class="flex gap-0.5 text-amber-400">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <blockquote class="mt-4 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                            "Before MobileShopBD, I used to manage orders on paper and Facebook messages. Now customers
                            order on my own website and pay by bKash automatically. Sales went up almost 40%."
                        </blockquote>
                        <figcaption class="mt-5 flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300">
                                RK
                            </span>
                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">Rahim Karim</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Owner, Gadget Hub · Sylhet</p>
                            </div>
                        </figcaption>
                    </figure>

                    <figure class="rounded-2xl border border-gray-200 bg-white p-7 shadow-soft dark:border-gray-800 dark:bg-gray-950">
                        <div class="flex gap-0.5 text-amber-400">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <blockquote class="mt-4 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                            "The inventory with serial number tracking changed everything for us. I know exactly which IMEI
                            is with which customer. Worth every taka."
                        </blockquote>
                        <figcaption class="mt-5 flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300">
                                NA
                            </span>
                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">Nasrin Akter</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Owner, TechBazaar · Dhaka</p>
                            </div>
                        </figcaption>
                    </figure>

                    <figure class="rounded-2xl border border-gray-200 bg-white p-7 shadow-soft dark:border-gray-800 dark:bg-gray-950">
                        <div class="flex gap-0.5 text-amber-400">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <blockquote class="mt-4 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                            "I'm not technical at all. I built my whole store in one afternoon and started getting orders
                            the same week. The support team even helped me import my catalog."
                        </blockquote>
                        <figcaption class="mt-5 flex items-center gap-3">
                            <span class="grid h-10 w-10 place-items-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300">
                                MH
                            </span>
                            <div>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">Mohammad Hossain</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Owner, iZone · Rajshahi</p>
                            </div>
                        </figcaption>
                    </figure>
                </div>
            </div>
        </section>

        {{-- ============ PRICING ============ --}}
        <section id="pricing" class="py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <span class="text-sm font-semibold text-[var(--brand)]">Simple pricing</span>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                        Plans that fit a real shop budget
                    </h2>
                    <p class="mt-4 text-lg text-gray-600 dark:text-gray-300">
                        Start free. Upgrade when your shop grows. No hidden charges — prices in BDT.
                    </p>
                </div>

                <div class="mt-16 grid gap-8 lg:grid-cols-3">
                    {{-- Free --}}
                    <div class="flex flex-col rounded-2xl border border-gray-200 bg-white p-8 shadow-soft dark:border-gray-800 dark:bg-gray-950">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Starter</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">For testing the waters</p>
                        <p class="mt-6">
                            <span class="text-4xl font-extrabold text-gray-900 dark:text-white">৳0</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">/month forever</span>
                        </p>
                        <ul class="mt-8 flex-1 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                            <li class="flex items-center gap-2"><span class="text-emerald-500">✓</span> Up to 20 products</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-500">✓</span> 50 orders / month</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-500">✓</span> bKash &amp; Nagad checkout</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-500">✓</span> Your own subdomain</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-500">✓</span> Community support</li>
                        </ul>
                        <x-ui.button as="a" href="{{ route('platform.signup') }}" variant="secondary" class="mt-8 w-full">
                            Start Free
                        </x-ui.button>
                    </div>

                    {{-- Growth (highlighted) --}}
                    <div class="relative flex flex-col rounded-2xl border-2 border-[var(--brand)] bg-white p-8 shadow-elevated dark:bg-gray-950">
                        <span
                            class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-[var(--brand)] px-4 py-1 text-xs font-bold text-white">
                            Most popular
                        </span>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Growth</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">For growing shops</p>
                        <p class="mt-6">
                            <span class="text-4xl font-extrabold text-gray-900 dark:text-white">৳999</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">/month</span>
                        </p>
                        <ul class="mt-8 flex-1 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                            <li class="flex items-center gap-2"><span class="text-emerald-500">✓</span> Unlimited products</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-500">✓</span> Unlimited orders</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-500">✓</span> IMEI / serial number tracking</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-500">✓</span> Coupons &amp; campaigns</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-500">✓</span> Sales analytics &amp; reports</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-500">✓</span> Priority support</li>
                        </ul>
                        <x-ui.button as="a" href="{{ route('platform.signup') }}" class="mt-8 w-full">
                            Start 14-Day Free Trial
                        </x-ui.button>
                    </div>

                    {{-- Pro --}}
                    <div class="flex flex-col rounded-2xl border border-gray-200 bg-white p-8 shadow-soft dark:border-gray-800 dark:bg-gray-950">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Pro</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">For multi-branch chains</p>
                        <p class="mt-6">
                            <span class="text-4xl font-extrabold text-gray-900 dark:text-white">৳2,499</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">/month</span>
                        </p>
                        <ul class="mt-8 flex-1 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                            <li class="flex items-center gap-2"><span class="text-emerald-500">✓</span> Everything in Growth</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-500">✓</span> Custom domain (.com.bd)</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-500">✓</span> Multiple staff accounts</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-500">✓</span> WhatsApp order integration</li>
                            <li class="flex items-center gap-2"><span class="text-emerald-500">✓</span> Dedicated account manager</li>
                        </ul>
                        <x-ui.button as="a" href="{{ route('platform.signup') }}" variant="secondary" class="mt-8 w-full">
                            Contact Sales
                        </x-ui.button>
                    </div>
                </div>

                <p class="mt-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    14-day free trial on every plan. Cancel anytime — your data stays yours.
                </p>
            </div>
        </section>

        {{-- ============ FAQ ============ --}}
        <section id="faq" class="bg-gray-50 py-24 dark:bg-gray-900">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <span class="text-sm font-semibold text-[var(--brand)]">Questions?</span>
                    <h2 class="mt-3 text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white sm:text-4xl">
                        Frequently asked questions
                    </h2>
                </div>

                <div class="mt-12 space-y-4">
                    @foreach ([
                        ['q' => 'Do I need any technical skills?', 'a' => 'No. If you can post on Facebook, you can run a MobileShopBD store. We handle hosting, security, and updates. Our support team helps you with setup, and can even import your existing product list for free.'],
                        ['q' => 'How do I get paid by my customers?', 'a' => 'Your customers can pay by bKash, Nagad, Rocket, debit/credit card, or cash on delivery. Each payment is matched to its order automatically, so you always know what has been paid.'],
                        ['q' => 'Can I sell from my existing Facebook page?', 'a' => 'Absolutely. Your store gets its own link that you can share on Facebook, WhatsApp, and Instagram. Many of our shop owners drive most of their traffic from social media.'],
                        ['q' => 'What happens after my 14-day free trial?', 'a' => 'You can continue on the free Starter plan, or pick a paid plan. If you choose not to continue, your store stays on our servers for a while and you can export all your data anytime.'],
                        ['q' => 'Can I use my own domain name?', 'a' => 'Yes, the Pro plan supports custom domains. We help you set up a .com.bd or other domain so your shop looks even more professional.'],
                        ['q' => 'Is my data safe?', 'a' => 'Yes. Your shop data is hosted on secure servers with backups, encrypted connections (HTTPS), and strict access controls. Only you and your staff can see your store data.'],
                    ] as $faq)
                        <div x-data="{ open: false }"
                            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-soft dark:border-gray-800 dark:bg-gray-950">
                            <button type="button" @click="open = !open"
                                class="flex w-full items-center justify-between gap-4 px-6 py-5 text-left">
                                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $faq['q'] }}</span>
                                <svg class="h-5 w-5 shrink-0 text-gray-400 transition-transform" :class="open && 'rotate-180'"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div x-show="open" x-cloak x-collapse>
                                <p class="px-6 pb-5 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                                    {{ $faq['a'] }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ============ FINAL CTA ============ --}}
        <section class="py-24">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="relative overflow-hidden rounded-3xl bg-[var(--brand)] px-6 py-16 text-center shadow-elevated sm:px-16">
                    <div class="pointer-events-none absolute inset-0 opacity-20"
                        style="background-image: radial-gradient(white 1px, transparent 1px); background-size: 24px 24px;">
                    </div>
                    <h2 class="relative text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                        Ready to take your mobile shop online?
                    </h2>
                    <p class="relative mx-auto mt-4 max-w-xl text-lg text-emerald-50">
                        Join 2,500+ shop owners across Bangladesh already selling on MobileShopBD. Your first 14 days are
                        free.
                    </p>
                    <div class="relative mt-8 flex justify-center">
                        <a href="{{ route('platform.signup') }}"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-white px-8 py-3.5 text-base font-bold text-[var(--brand)] shadow-lg transition hover:brightness-95">
                            Create Your Shop — It's Free
                        </a>
                    </div>
                    <p class="relative mt-4 text-sm text-emerald-100">No credit card required · Setup in minutes</p>
                </div>
            </div>
        </section>

        {{-- ============ FOOTER ============ --}}
        <footer class="border-t border-gray-200 bg-white py-12 dark:border-gray-800 dark:bg-gray-950">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
                    <a href="#top" class="flex items-center gap-2">
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-[var(--brand)] text-white">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </span>
                        <span class="font-bold text-gray-900 dark:text-white">MobileShop<span
                                class="text-[var(--brand)]">BD</span></span>
                    </a>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        © {{ date('Y') }} MobileShopBD. Made with ❤️ in Bangladesh.
                    </p>
                    <div class="flex items-center gap-6 text-sm text-gray-500 dark:text-gray-400">
                        <a href="{{ route('platform.signup') }}" class="hover:text-[var(--brand)]">Start Free Trial</a>
                        <span>Dhaka, Bangladesh</span>
                    </div>
                </div>
            </div>
        </footer>
    </div>
@endsection
