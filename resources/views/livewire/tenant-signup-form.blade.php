<div class="min-h-screen" style="--brand: #059669">
    <div class="grid min-h-screen lg:grid-cols-2">

        {{-- ============ LEFT / MARKETING PANEL ============ --}}
        <div class="relative hidden overflow-hidden bg-[var(--brand)] lg:flex lg:flex-col lg:justify-between lg:p-12">
            <div class="pointer-events-none absolute inset-0 opacity-20"
                style="background-image: radial-gradient(white 1px, transparent 1px); background-size: 26px 26px;">
            </div>
            <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-white/10"></div>

            <a href="{{ route('platform.home') }}" class="relative flex items-center gap-2">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-white text-[var(--brand)]">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </span>
                <span class="text-xl font-bold text-white">MobileShop<span class="text-emerald-100">BD</span></span>
            </a>

            <div class="relative">
                <h1 class="text-4xl font-extrabold leading-tight tracking-tight text-white">
                    Start selling online<br />
                    in just 2 minutes.
                </h1>
                <p class="mt-4 max-w-md text-lg leading-relaxed text-emerald-50">
                    Join thousands of mobile shops across Bangladesh. Create your free store and start taking bKash,
                    Nagad, and COD orders today.
                </p>

                <ul class="mt-8 space-y-4">
                    <li class="flex items-center gap-3 text-sm font-medium text-white">
                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-white/20 text-white">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </span>
                        Free 14-day trial — no credit card required
                    </li>
                    <li class="flex items-center gap-3 text-sm font-medium text-white">
                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-white/20 text-white">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </span>
                        Your own store link: yourshop.mobileshopbd.com
                    </li>
                    <li class="flex items-center gap-3 text-sm font-medium text-white">
                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-white/20 text-white">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </span>
                        No tech skills needed — setup is guided
                    </li>
                    <li class="flex items-center gap-3 text-sm font-medium text-white">
                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-white/20 text-white">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </span>
                        Bengali-friendly support, 7 days a week
                    </li>
                </ul>
            </div>

            <div class="relative flex items-center gap-3">
                <div class="flex -space-x-3">
                    <span class="grid h-10 w-10 place-items-center rounded-full border-2 border-[var(--brand)] bg-emerald-200 text-xs font-bold text-emerald-800">RK</span>
                    <span class="grid h-10 w-10 place-items-center rounded-full border-2 border-[var(--brand)] bg-amber-200 text-xs font-bold text-amber-800">NA</span>
                    <span class="grid h-10 w-10 place-items-center rounded-full border-2 border-[var(--brand)] bg-rose-200 text-xs font-bold text-rose-800">MH</span>
                </div>
                <p class="text-sm text-emerald-50">
                    <span class="font-bold text-white">2,500+</span> shop owners already on board
                </p>
            </div>
        </div>

        {{-- ============ RIGHT / FORM PANEL ============ --}}
        <div class="flex flex-col justify-center bg-gray-50 px-4 py-12 dark:bg-gray-950 sm:px-8 lg:px-16">
            <div class="mx-auto w-full max-w-md">

                {{-- Mobile brand (shown only below lg) --}}
                <a href="{{ route('platform.home') }}" class="mb-8 flex items-center gap-2 lg:hidden">
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-[var(--brand)] text-white">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </span>
                    <span class="text-lg font-bold text-gray-900 dark:text-white">MobileShop<span
                            class="text-[var(--brand)]">BD</span></span>
                </a>

                <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                    Create your free store
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Already have an account?
                    <a href="{{ route('filament.platform.auth.login') }}"
                        class="font-semibold text-[var(--brand)] hover:underline">Sign in</a>
                </p>

                <form wire:submit="register" class="mt-8 space-y-5">
                    <x-ui.input name="business_name" label="Business name" placeholder="e.g. TechBazaar Dhaka"
                        wire:model.live.blur="business_name" :error="$errors->first('business_name')" />

                    <div>
                        <label for="subdomain"
                            class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Choose your store
                            link</label>
                        <div class="flex items-center">
                            <input id="subdomain" name="subdomain" wire:model.live.debounce.500ms="subdomain"
                                placeholder="yourshop"
                                class="w-full rounded-l-lg border border-gray-300 px-3 py-2.5 text-sm transition focus:border-[var(--brand)] focus:ring-[var(--brand)] dark:border-gray-700 dark:bg-gray-800 {{ $errors->has('subdomain') ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : '' }}" />
                            <span
                                class="shrink-0 rounded-r-lg border border-l-0 border-gray-300 bg-gray-100 px-3 py-2.5 text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                .{{ config('tenancy.central_domain') }}
                            </span>
                        </div>
                        @if ($subdomain && !$errors->has('subdomain'))
                            <p class="mt-1 flex items-center gap-1 text-sm text-emerald-600">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ $subdomain }}.{{ config('tenancy.central_domain') }} is available
                            </p>
                        @endif
                        @if ($errors->has('subdomain'))
                            <p class="mt-1 text-sm text-red-500">{{ $errors->first('subdomain') }}</p>
                        @endif
                    </div>

                    <x-ui.input name="owner_name" label="Your name" placeholder="e.g. Rahim Karim"
                        wire:model.live.blur="owner_name" :error="$errors->first('owner_name')" />
                    <x-ui.input name="owner_email" type="email" label="Email" placeholder="you@example.com"
                        wire:model.live.blur="owner_email" :error="$errors->first('owner_email')" />
                    <x-ui.input name="owner_phone" type="tel" label="Mobile number" placeholder="e.g. 01712345678"
                        wire:model.live.blur="owner_phone" :error="$errors->first('owner_phone')" />

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-ui.input name="password" type="password" label="Password" placeholder="Min 8 characters"
                            wire:model="password" :error="$errors->first('password')" />
                        <x-ui.input name="password_confirmation" type="password" label="Confirm password"
                            placeholder="Repeat password" wire:model="password_confirmation" />
                    </div>

                    <x-ui.button type="submit" variant="primary" size="lg" class="w-full" loading-target="register">
                        Create My Store
                    </x-ui.button>

                    <p class="text-center text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                        By creating a store you agree to our Terms of Service and Privacy Policy.
                        Your 14-day free trial starts automatically.
                    </p>
                </form>

                <div class="mt-8 flex items-center justify-center gap-2 text-xs text-gray-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    Secure &amp; encrypted. We'll never share your data.
                </div>
            </div>
        </div>
    </div>
</div>
