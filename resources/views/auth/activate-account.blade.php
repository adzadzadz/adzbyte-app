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

                <p id="password-requirements" class="adz-activation-copy">Use at least 12 characters, including uppercase and lowercase letters, a number, and a symbol.</p>

                <form method="POST" action="{{ request()->fullUrl() }}" class="adz-activation-form">
                    @csrf

                    <div class="adz-activation-field">
                        <label for="password" class="adz-activation-label">Password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            minlength="12"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                            aria-describedby="password-requirements{{ $errors->has('password') ? ' password-error' : '' }}"
                            autocomplete="new-password"
                            required
                            autofocus
                            class="adz-activation-input"
                        >
                        @error('password')
                            <p id="password-error" class="adz-activation-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="adz-activation-field">
                        <label for="password_confirmation" class="adz-activation-label">Confirm password</label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}"
                            @if ($errors->has('password_confirmation')) aria-describedby="password-confirmation-error" @endif
                            autocomplete="new-password"
                            required
                            class="adz-activation-input"
                        >
                        @error('password_confirmation')
                            <p id="password-confirmation-error" class="adz-activation-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="adz-activation-label"><input type="checkbox" aria-controls="password password_confirmation" onchange="for (const id of ['password', 'password_confirmation']) document.getElementById(id).type = this.checked ? 'text' : 'password'"> Show passwords</label>

                    <button type="submit" class="adz-activation-submit">
                        Activate account
                    </button>
                </form>
                <nav aria-label="Account help" class="adz-activation-copy">
                    <a href="{{ route('filament.account.auth.login') }}" class="underline">Sign in</a>
                    · <a href="{{ route('filament.account.auth.password-reset.request') }}" class="underline">Reset password</a>
                </nav>
            </section>
        </main>

        @filamentScripts
    </body>
</html>
