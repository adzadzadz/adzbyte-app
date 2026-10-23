<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="fi dark">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Account link unavailable · {{ config('app.name') }}</title>
    @filamentStyles
    @vite('resources/css/filament/theme.css')
</head>
<body class="fi-body adz-activation-body">
    <main class="adz-activation-shell"><section class="adz-activation-card">
        <h1 class="adz-activation-heading">This account link is unavailable</h1>
        <p class="adz-activation-copy">The link may have expired or been changed. Request fresh account instructions and use the newest email you receive.</p>
        <p><a class="adz-portal-link" href="{{ route('registration.show') }}">Request account instructions</a></p>
        <p><a class="adz-portal-link" href="{{ route('filament.account.auth.password-reset.request') }}">Reset an existing account password</a></p>
        <p><a class="adz-portal-link" href="{{ route('filament.account.auth.login') }}">Return to sign in</a></p>
    </section></main>
</body>
</html>
