{{-- resources/views/storefront/partials/sections/newsletter-cta.blade.php --}}
<div class="rounded-2xl bg-[var(--brand)] px-6 sm:px-10 py-10 sm:py-12 text-white text-center">
    <h2 class="text-2xl sm:text-3xl font-bold tracking-tight">{{ $section->title ?: 'Never Miss a Deal' }}</h2>
    <p class="mt-2 text-white/85 max-w-md mx-auto">
        {{ $section->config['subtitle'] ?? 'Subscribe for new arrivals, price drops, and exclusive offers straight to your inbox.' }}
    </p>

    <form action="{{ route('storefront.newsletter.subscribe') }}" method="POST"
        class="mt-6 flex flex-col sm:flex-row gap-3 max-w-md mx-auto">
        @csrf
        <label for="newsletter-email" class="sr-only">Email address</label>
        <input id="newsletter-email" type="email" name="email" required placeholder="you@example.com"
            class="flex-1 px-4 py-3 rounded-full text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-white/60">
        <button type="submit"
            class="px-6 py-3 rounded-full bg-gray-900 text-white font-medium hover:bg-gray-800 transition flex-shrink-0">
            Subscribe
        </button>
    </form>

    @error('email')
        <p class="mt-3 text-sm text-red-100">{{ $message }}</p>
    @enderror
</div>
