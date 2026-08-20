@extends('storefront.layout')

@section('title', 'Login - ' . tenant()->name)

@section('content')
    <div class="max-w-sm mx-auto px-4 py-16">
        <h1 class="text-2xl font-bold mb-6">Login</h1>
        <form method="POST" action="{{ route('storefront.login') }}" class="space-y-4">
            @csrf
            <input type="email" name="email" placeholder="Email"
                class="w-full rounded border border-gray-300 dark:bg-gray-800 dark:border-gray-700" required>
            <input type="password" name="password" placeholder="Password"
                class="w-full rounded border border-gray-300 dark:bg-gray-800 dark:border-gray-700" required>
            @error('email')
                <p class="text-red-500 text-sm">{{ $message }}</p>
            @enderror
            <button class="w-full py-3 rounded-xl bg-[var(--brand)] text-white font-medium">Login</button>
        </form>
        <p class="text-sm text-center mt-4">No account? <a href="{{ route('storefront.register') }}"
                class="text-[var(--brand)]">Register</a></p>
    </div>
@endsection
