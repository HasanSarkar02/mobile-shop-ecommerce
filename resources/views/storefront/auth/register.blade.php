@extends('storefront.layout')

@section('title', 'Register - ' . tenant()->name)

@section('content')
<div class="min-h-[calc(100vh-240px)] flex items-center py-8 sm:py-12">
    <div class="w-full max-w-md mx-auto px-4 sm:px-6">
        <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-100 dark:border-gray-800 shadow-soft p-6 sm:p-8 lg:p-10">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Create account</h1>
                        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Join {{ tenant()->name }} — it takes less than a minute</p>
                    </div>
                    <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-gray-900 dark:bg-white px-2.5 py-1 text-xs font-semibold text-white dark:text-gray-900">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Free forever
                    </span>
                </div>

                <form method="POST" action="{{ route('storefront.register.submit') }}" class="mt-7 space-y-4">
                    @csrf

                    <div class="space-y-1.5">
                        <label for="name" class="text-xs font-semibold tracking-wide uppercase text-gray-600 dark:text-gray-300">Full name</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 19.5a14.02 14.02 0 018.25-3.75 14.02 14.02 0 018.25 3.75M15 6.75a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </span>
                            <input id="name" type="text" name="name" value="{{ old('name') }}" placeholder="Ayesha Rahman" autocomplete="name" required
                                class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 pl-10 pr-3 py-3 text-sm placeholder:text-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 outline-none transition">
                        </div>
                        @error('name')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label for="email" class="text-xs font-semibold tracking-wide uppercase text-gray-600 dark:text-gray-300">Email</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91A2.25 2.25 0 012.25 7.014V6.75"/></svg>
                                </span>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email" required
                                    class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 pl-10 pr-3 py-3 text-sm placeholder:text-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 outline-none transition">
                            </div>
                            @error('email')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <div class="space-y-1.5">
                            <label for="phone" class="text-xs font-semibold tracking-wide uppercase text-gray-600 dark:text-gray-300">Phone <span class="normal-case font-normal text-gray-400">(optional)</span></label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h1.5a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c0-.516-.351-.966-.852-1.091l-4.423-1.106a1.125 1.125 0 00-1.173.417l-.97 1.293a1.125 1.125 0 01-1.21.38 12.035 12.035 0 01-7.143-7.143 1.125 1.125 0 01.38-1.21l1.293-.97a1.125 1.125 0 00.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>
                                </span>
                                <input id="phone" type="text" name="phone" value="{{ old('phone') }}" placeholder="01XXXXXXXXX" autocomplete="tel"
                                    class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 pl-10 pr-3 py-3 text-sm placeholder:text-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 outline-none transition">
                            </div>
                            @error('phone')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="password" class="text-xs font-semibold tracking-wide uppercase text-gray-600 dark:text-gray-300">Password</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-gray-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 00-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                            </span>
                            <input id="password" type="password" name="password" placeholder="At least 8 characters" autocomplete="new-password" required
                                class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 pl-10 pr-3 py-3 text-sm placeholder:text-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:border-[var(--brand)] focus:ring-4 focus:ring-[var(--brand)]/10 outline-none transition">
                        </div>
                        <p class="text-xs text-gray-400 dark:text-gray-500">Use 8+ characters with a mix of letters & numbers.</p>
                        @error('password')<p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[var(--brand)] px-4 py-3.5 text-sm font-semibold text-white shadow-sm shadow-black/5 hover:brightness-[0.98] active:brightness-95 focus:outline-none focus-visible:ring-4 focus-visible:ring-[var(--brand)]/20 transition">
                        Create account
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                    </button>

                    <p class="text-center text-sm text-gray-600 dark:text-gray-300">
                        Already have an account?
                        <a href="{{ route('storefront.login') }}" class="font-semibold text-[var(--brand)] hover:underline underline-offset-2">Login</a>
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
