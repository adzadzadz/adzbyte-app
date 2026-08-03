<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="fi dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Activate account — {{ config('app.name') }}</title>

        @filamentStyles
        @vite('resources/css/filament/theme.css')
    </head>
    <body class="fi-body adz-activation-body">
        <main class="adz-activation-shell">
            <section class="adz-activation-card">
                <img
                    src="{{ asset('images/brand/adzbyte-logo-transparent.png') }}"
                    alt="Adzbyte"
                    width="700"
                    height="300"
                    class="adz-activation-logo"
                >

                @include('filament.branding.panel-context', [
                    'context' => \App\Support\Filament\AdzbytePanel::CUSTOMER_ACCOUNT,
                    'placement' => 'simple',
                ])

                <h1 class="adz-activation-heading">Activate your account</h1>
                <p class="adz-activation-copy">
                    Set a password for <strong>{{ $user->email }}</strong> to continue to your customer account.
                </p>

                <form method="POST" action="{{ request()->fullUrl() }}" class="adz-activation-form">
                    @csrf

                    <div class="adz-activation-field">
                        <label for="password" class="adz-activation-label">Password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            required
                            autofocus
                            class="adz-activation-input"
                        >
                        @error('password')
                            <p class="adz-activation-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="adz-activation-field">
                        <label for="password_confirmation" class="adz-activation-label">Confirm password</label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="adz-activation-input"
                        >
                    </div>

                    <button type="submit" class="adz-activation-submit">
                        Activate account
                    </button>
                </form>
            </section>
        </main>

        @filamentScripts
    </body>
</html>
