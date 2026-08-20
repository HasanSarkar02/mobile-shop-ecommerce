<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set up your {{ $tenant->name }} admin account</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-900">
    <main class="mx-auto flex min-h-screen w-full max-w-md items-center px-4 py-12">
        <section class="w-full rounded-2xl bg-white p-8 shadow-sm ring-1 ring-gray-200">
            <p class="text-sm font-semibold uppercase tracking-wide text-emerald-600">{{ $tenant->name }}</p>
            <h1 class="mt-2 text-2xl font-bold">{{ $isTransfer ? 'Accept ownership transfer' : 'Set up your admin password' }}</h1>
            <p class="mt-2 text-sm text-gray-600">
                {{ $isTransfer ? 'Create a password to accept the new primary-owner role for this store.' : 'Create a password to access your store admin panel.' }}
                This setup link expires on {{ $expiresAt->toDateTimeString() }}.
            </p>

            <form method="POST" action="{{ route('storefront.owner-invitation.accept', ['token' => $token]) }}" class="mt-8 space-y-5">
                @csrf

                <div>
                    <label for="password" class="mb-1 block text-sm font-medium">Password</label>
                    <input id="password" name="password" type="password" required minlength="8"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm font-medium">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                </div>

                <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                    Continue to admin panel
                </button>
            </form>
        </section>
    </main>
</body>
</html>
