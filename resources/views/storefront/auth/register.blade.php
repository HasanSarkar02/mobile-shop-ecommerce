@extends('storefront.layout')

@section('title', 'Register - ' . tenant()->name)

@section('content')
    <div class="max-w-sm mx-auto px-4 py-16">
        <h1 class="text-2xl font-bold mb-6">Register</h1>
        <form method="POST" action="{{ route('storefront.register.submit') }}" class="space-y-4">
            @csrf
            <input type="text" name="name" placeholder="Full name"
                class="w-full rounded border border-gray-300 dark:bg-gray-800 dark:border-gray-700" required>
            <input type="email" name="email" placeholder="Email"
                class="w-full rounded border border-gray-300 dark:bg-gray-800 dark:border-gray-700" required>
            <input type="text" name="phone" placeholder="Phone"
                class="w-full rounded border border-gray-300 dark:bg-gray-800 dark:border-gray-700">
            <input type="password" name="password" placeholder="Password"
                class="w-full rounded border border-gray-300 dark:bg-gray-800 dark:border-gray-700" required>
            <button class="w-full py-3 rounded-xl bg-[var(--brand)] text-white font-medium">Create Account</button>
        </form>
    </div>
@endsection
