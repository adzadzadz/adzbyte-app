<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="fi dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Create your account · Adzbyte</title>
    @filamentStyles
    @vite('resources/css/filament/theme.css')
</head>
<body class="fi-body adz-activation-body">
    <main class="adz-activation-shell">
        <section class="adz-activation-card">
            <a href="{{ $website }}"><img src="{{ asset('images/brand/adzbyte-logo-transparent.png') }}" width="700" height="300" alt="Adzbyte" class="adz-activation-logo"></a>
            @include('filament.branding.panel-context', [
                'context' => \App\Support\Filament\AdzbytePanel::CUSTOMER_ACCOUNT,
                'placement' => 'simple',
            ])
            <h1 class="adz-activation-heading">Create your account</h1>
            <p class="adz-activation-copy">We’ll email a secure link to verify your address and set your password.</p>
            @if (session('registration_sent'))
                <p role="status" class="adz-activation-copy">{{ session('registration_sent') }}</p>
            @endif
            <form method="post" action="{{ route('registration.store') }}" class="adz-activation-form">
                @csrf
                <div class="adz-activation-field">
                    <label for="name" class="adz-activation-label">Name</label>
                    <input id="name" name="name" autocomplete="name" required maxlength="255" value="{{ old('name') }}" class="adz-activation-input" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @if ($errors->has('name')) aria-describedby="name-error" @endif>
                    @error('name')<p id="name-error" role="alert" class="adz-activation-error">{{ $message }}</p>@enderror
                </div>
                <div class="adz-activation-field">
                    <label for="email" class="adz-activation-label">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="email" required maxlength="255" value="{{ old('email') }}" class="adz-activation-input" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @if ($errors->has('email')) aria-describedby="email-error" @endif>
                    @error('email')<p id="email-error" role="alert" class="adz-activation-error">{{ $message }}</p>@enderror
                </div>
                @foreach ($errors->getMessages() as $field => $messages)
                    @if (! in_array($field, ['name', 'email'], true))
                        @foreach ($messages as $message)<p role="alert" class="adz-activation-error">{{ $message }}</p>@endforeach
                    @endif
                @endforeach
                <button type="submit" class="adz-activation-submit">Send account link</button>
            </form>
            <p class="adz-activation-copy">Read our <a href="{{ $website }}/privacy" class="underline">Privacy Policy</a> and <a href="{{ $website }}/terms" class="underline">Terms</a>.</p>
            <nav aria-label="Account help" class="adz-activation-copy"><a href="{{ route('filament.account.auth.login') }}" class="underline">Sign in</a> · <a href="{{ route('filament.account.auth.password-reset.request') }}" class="underline">Reset password</a> · <a href="{{ route('cart.show') }}" class="underline">Your cart</a></nav>
        </section>
    </main>
    @filamentScripts
</body>
</html>
