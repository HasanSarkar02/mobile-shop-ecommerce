<div class="max-w-md mx-auto bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-8">
    <form wire:submit="register" class="space-y-4">
        <x-ui.input name="business_name" label="Business name" wire:model.live.blur="business_name" :error="$errors->first('business_name')" />

        <div>
            <x-ui.input name="subdomain" label="Choose your subdomain" wire:model.live.debounce.500ms="subdomain"
                :error="$errors->first('subdomain')" />
            @if ($subdomain && !$errors->has('subdomain'))
                <p class="text-sm text-green-600 mt-1">{{ $subdomain }}.{{ config('tenancy.central_domain') }} is
                    available ✓</p>
            @endif
        </div>

        <x-ui.input name="owner_name" label="Your name" wire:model.live.blur="owner_name" :error="$errors->first('owner_name')" />
        <x-ui.input name="owner_email" type="email" label="Email" wire:model.live.blur="owner_email"
            :error="$errors->first('owner_email')" />
        <x-ui.input name="password" type="password" label="Password" wire:model="password" :error="$errors->first('password')" />
        <x-ui.input name="password_confirmation" type="password" label="Confirm password"
            wire:model="password_confirmation" />

        <x-ui.button type="submit" variant="primary" size="lg" class="w-full" loading-target="register">
            Create My Store
        </x-ui.button>
    </form>
</div>
