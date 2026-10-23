<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Create your account · Adzbyte</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-[#0a0f17] font-sans text-slate-100 antialiased">
    <main class="mx-auto max-w-lg px-5 py-10 [overflow-wrap:anywhere] sm:py-16">
        <a href="{{ $website }}"><img src="{{ asset('images/brand/adzbyte-logo-transparent.png') }}" width="160" height="45" alt="Adzbyte" class="mb-10 h-auto w-40"></a>
        <h1 class="text-3xl font-semibold">Create your account</h1>
        <p class="mt-4 text-slate-300">We’ll email a secure link to verify your address and set your password.</p>
        @if (session('registration_sent'))
            <p role="status" class="mt-6 rounded-xl border border-emerald-800 bg-emerald-950/50 p-4">{{ session('registration_sent') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="mt-6 rounded-xl border border-rose-800 bg-rose-950/50 p-4"><p>Check the details below.</p><ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        <form method="post" action="{{ route('registration.store') }}" class="mt-8 space-y-6">
            @csrf
            <div><label for="name" class="block font-medium">Name</label><input id="name" name="name" autocomplete="name" required maxlength="255" value="{{ old('name') }}" class="mt-2 w-full rounded-lg border border-slate-600 bg-slate-900 px-4 py-3 focus-visible:outline-2 focus-visible:outline-offset-4"></div>
            <div><label for="email" class="block font-medium">Email address</label><input id="email" name="email" type="email" autocomplete="email" required maxlength="255" value="{{ old('email') }}" class="mt-2 w-full rounded-lg border border-slate-600 bg-slate-900 px-4 py-3 focus-visible:outline-2 focus-visible:outline-offset-4"></div>
            <button type="submit" class="w-full rounded-lg bg-[#8139ff] px-4 py-3 font-medium hover:bg-violet-600 focus-visible:outline-2 focus-visible:outline-offset-4">Send account link</button>
        </form>
        <p class="mt-6 text-sm text-slate-400">Read our <a href="{{ $website }}/privacy" class="underline">Privacy Policy</a> and <a href="{{ $website }}/terms" class="underline">Terms</a>.</p>
        <nav aria-label="Account help" class="mt-7 flex flex-wrap gap-5"><a href="{{ route('filament.account.auth.login') }}" class="underline">Sign in</a><a href="{{ route('filament.account.auth.password-reset.request') }}" class="underline">Reset password</a><a href="{{ route('cart.show') }}" class="underline">Your cart</a></nav>
    </main>
</body>
</html>
