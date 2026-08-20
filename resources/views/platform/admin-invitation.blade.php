<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set up Platform Admin access</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
    <main class="mx-auto flex min-h-screen w-full max-w-md items-center px-4 py-12">
        <section class="w-full rounded-2xl bg-white p-8 shadow-sm ring-1 ring-gray-200">
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-600">MobileShop Platform</p>
            <h1 class="mt-2 text-2xl font-bold">Set up Platform Admin access</h1>
            <p class="mt-2 text-sm text-gray-600">Create a secure password to continue.</p>
            <form method="POST" action="{{ url()->current() }}" class="mt-8 space-y-5">
                @csrf
                <div>
                    <label for="password" class="mb-1 block text-sm font-medium">Password</label>
                    <input id="password" name="password" type="password" required minlength="12"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm font-medium">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                    Continue
                </button>
            </form>
        </section>
    </main>
</body>
</html>
