@extends('storefront.account.layout')

@section('title', 'Profile - ' . tenant()->name)

@section('account-content')
    <h1 class="text-xl font-bold mb-4">Profile</h1>
    <form method="POST" action="{{ route('storefront.account.profile.update') }}" class="space-y-3 max-w-sm">
        @csrf @method('PUT')
        <input name="name" value="{{ auth('customer')->user()->name }}"
            class="w-full rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700">
        <input name="phone" value="{{ auth('customer')->user()->phone }}"
            class="w-full rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700">
        <button class="py-2 px-4 rounded-lg bg-[var(--brand)] text-white">Save</button>
    </form>

    <h2 class="text-lg font-bold mt-8 mb-4">Change Password</h2>
    <form method="POST" action="{{ route('storefront.account.password.update') }}" class="space-y-3 max-w-sm">
        @csrf @method('PUT')
        <input type="password" name="current_password" placeholder="Current password"
            class="w-full rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700">
        <input type="password" name="password" placeholder="New password"
            class="w-full rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700">
        <input type="password" name="password_confirmation" placeholder="Confirm new password"
            class="w-full rounded border-gray-300 dark:bg-gray-800 dark:border-gray-700">
        <button class="py-2 px-4 rounded-lg bg-[var(--brand)] text-white">Change Password</button>
    </form>
@endsection
