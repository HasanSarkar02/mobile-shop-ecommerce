@extends('storefront.layout')

@section('title', 'Login - ' . tenant()->name)

@section('content')
<div class="min-h-[calc(100vh-240px)] flex items-center py-8 sm:py-12">
    <div class="w-full max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid lg:grid-cols-[1.05fr_0.95fr] gap-6 lg:gap-0 items-stretch">
            {{-- Left: brand story / benefits --}}
            <div class="order-2 lg:order-1 relative overflow-hidden rounded-3xl lg:rounded-r-none border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 sm:p-8 lg:p-10 flex flex-col">
                <div class="absolute -top-24 -left-24 w-72 h-72 rounded-full bg-[var(--brand)]/[0.07] blur-3xl pointer-events-none"></div>
                <div class="absolute -bottom-20 -right-20 w-64 h-64 rounded-full bg-emerald-100/40 dark:bg-emerald-900/20 blur-2xl pointer-events-none"></div>

                <div class="relative">
                    <div class="inline-flex items-center gap-2 rounded-full border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50 px-3 py-1.5 text-xs font-medium text-gray-600 dark:text-gray-300">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        {{ tenant()->name }} — open today
                    </div>

                    <h2 class="mt-6 text-[28px] sm:text-[32px] font-bold tracking-tight leading-[0.95] text-gray-900 dark:text-white">
                        Welcome back.<br>
                        <span class="font-light text-gray-500 dark:text-gray-400">Fresh picks await.</span>
                    </h2>
                    <p class="mt-3 text-sm leading-relaxed text-gray-500 dark:text-gray-400 max-w-sm">
                        Sign in to track orders, manage addresses and check out faster. Your cart is saved across devices.
                    </p>

                    <div class="mt-8 space-y-4">
                        <div class="flex gap-3">
                            <span class="flex-shrink-0 w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-100 dark:border-emerald-900/40 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/></svg>
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Same-day delivery</p>
                                <p class="text-xs text-gray-500">Dhaka metro, 10am — 8pm</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <span class="flex-shrink-0 w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-100 dark:border-amber-900/30 flex items-center justify-center text-amber-600">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Secure & flexible payment</p>
                                <p class="text-xs text-gray-500">Cash, card & mobile banking</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <span class="flex-shrink-0 w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-950/30 border border-sky-100 dark:border-sky-900/30 flex items-center justify-center text-sky-600">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182"/></svg>
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Easy returns</p>
                                <p class="text-xs text-gray-500">No questions asked, 7 days</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 rounded-2xl bg-gray-50 dark:bg-gray-800/50 border border-gray-100 dark:border-gray-800 p-4 flex items-center gap-3">
                        <div class="flex -space-x-2">
                            <img src="https://i.pravatar.cc/100?img=1" alt="" class="w-7 h-7 rounded-full border-2 border-white dark:border-gray-900 object-cover">
                            <img src="https://i.pravatar.cc/100?img=2" alt="" class="w-7 h-7 rounded-full border-2 border-white dark:border-gray-900 object-cover">
                            <img src="https://i.pravatar.cc/100?img=3" alt="" class="w-7 h-7 rounded-full border-2 border-white dark:border-gray-900 object-cover">
                        </div>
                        <p class="text-xs leading-snug text-gray-600 dark:text-gray-300"><span class="font-semibold text-gray-900 dark:text-white">12k+</span> shoppers trust {{ tenant()->name }} for daily essentials</p>
                    </div>
                </div>

                <p class="relative mt-auto pt-6 text-xs text-gray-400 dark:text-gray-500">
                    By continuing you agree to our <a href="#" class="underline decoration-gray-300 underline-offset-2 hover:text-gray-600">Terms</a> & <a href="#" class="underline decoration-gray-300 underline-offset-2 hover:text-gray-600">Privacy</a>.
                </p>
            </div>

            {{-- Right: form --}}
            <div class="order-1 lg:order-2 relative bg-white dark:bg-gray-900 rounded-3xl lg:rounded-l-none border border-gray-100 dark:border-gray-800 shadow-soft p-6 sm:p-8 lg:p-10">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Login</h1>
                        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Enter your email and password to continue</p>
                    </div>
                    <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900/40 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Secure
                    </span>
                </div>

                <form method="POST" action="{{ route('storefront.login') }}" class="mt-8 space-y-4">
                    @csrf

                    <div class="space-y-1.5">
                        <label for="email" class="text-xs font-semibold tracking-wide uppercase text-gray-600 dark:text-gray-300">Email</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91A2.25 2.25 0 012.25 7.014V6.75"/></svg>
                            </span>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email" required
                                class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 pl-10 pr-3 py-3 text-sm placeholder:text-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 outline-none transition">
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label for="password" class="text-xs font-semibold tracking-wide uppercase text-gray-600 dark:text-gray-300">Password</label>
                            <a href="#" class="text-xs font-medium text-[var(--brand)] hover:underline underline-offset-2">Forgot?</a>
                        </div>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 00-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                            </span>
                            <input id="password" type="password" name="password" placeholder="••••••••" autocomplete="current-password" required
                                class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 pl-10 pr-3 py-3 text-sm placeholder:text-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 outline-none transition">
                        </div>
                    </div>

                    @error('email')
                        <div class="rounded-xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-900/40 px-3 py-2.5 flex gap-2">
                            <svg class="w-4 h-4 text-red-500 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                            <p class="text-sm text-red-700 dark:text-red-300">{{ $message }}</p>
                        </div>
                    @enderror

                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[var(--brand)] px-4 py-3.5 text-sm font-semibold text-white shadow-sm shadow-black/5 hover:brightness-[0.98] active:brightness-95 focus:outline-none focus-visible:ring-4 focus-visible:ring-[var(--brand)]/20 transition">
                        Login
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </button>

                    <div class="relative py-2">
                        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-100 dark:border-gray-800"></div></div>
                        <div class="relative flex justify-center"><span class="bg-white dark:bg-gray-900 px-3 text-xs text-gray-400">or</span></div>
                    </div>

                    <p class="text-center text-sm text-gray-600 dark:text-gray-300">
                        No account?
                        <a href="{{ route('storefront.register') }}" class="font-semibold text-[var(--brand)] hover:underline underline-offset-2">Create one</a>
                    </p>
                </form>

                <p class="mt-6 flex items-center justify-center gap-1.5 text-xs text-gray-400 dark:text-gray-500">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 00-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                    Encrypted & protected — 256-bit SSL
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
