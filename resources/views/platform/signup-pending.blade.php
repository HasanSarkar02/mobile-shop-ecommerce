@extends('platform.layout')

@section('title', 'Application under review — MobileShop BD')

@section('content')
    <div class="flex min-h-screen items-center justify-center px-4 py-16" style="--brand: #059669">
        <div
            class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-elevated dark:border-gray-800 dark:bg-gray-950">
            <span
                class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-400">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </span>

            <h1 class="mt-5 text-2xl font-extrabold tracking-tight text-gray-900 dark:text-white">
                Your shop is under review
            </h1>

            <p class="mt-3 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                Thanks for signing up! Our team is reviewing your application. You will receive an email as soon as your
                shop has been approved — this usually takes less than a day.
            </p>

            <x-ui.button as="a" href="{{ route('platform.home') }}" variant="secondary" class="mt-6 w-full">
                Back to home
            </x-ui.button>
        </div>
    </div>
@endsection