<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Activate account — {{ config('app.name') }}</title>

        @filamentStyles
    </head>
    <body class="fi-body min-h-screen bg-gray-50 text-gray-950 antialiased dark:bg-gray-950 dark:text-white">
        <main class="mx-auto flex min-h-screen w-full max-w-md items-center px-6 py-12">
            <section class="w-full rounded-xl bg-white p-8 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h1 class="text-2xl font-semibold">Activate your account</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Set a password for <strong>{{ $user->email }}</strong> to continue to your customer account.
                </p>

                <form method="POST" action="{{ request()->fullUrl() }}" class="mt-8 space-y-6">
                    @csrf

                    <div>
                        <label for="password" class="block text-sm font-medium">Password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            required
                            autofocus
                            class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-950 shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >
                        @error('password')
                            <p class="mt-2 text-sm text-danger-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium">Confirm password</label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="mt-2 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-gray-950 shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        >
                    </div>

                    <button type="submit" class="w-full rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500">
                        Activate account
                    </button>
                </form>
            </section>
        </main>

        @filamentScripts
    </body>
</html>
