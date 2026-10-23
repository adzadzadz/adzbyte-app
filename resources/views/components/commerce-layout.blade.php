@props(['title'])
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · Adzbyte Solutions</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-[#0a0f17] font-sans text-slate-100 antialiased">
    <a href="#content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:bg-slate-800 focus:p-3">Skip to content</a>
    <header class="border-b border-slate-800 px-5 py-5"><nav aria-label="Account commerce" class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-5"><a href="/" aria-label="Adzbyte Solutions portal"><img src="{{ asset('images/brand/adzbyte-logo-transparent.png') }}" width="160" height="45" alt="Adzbyte Solutions" class="h-auto w-36"></a><div class="flex flex-wrap gap-5">@unless (\App\Support\Filament\CommerceNavigation::locked())<a href="{{ route('cart.show') }}" class="underline">Cart</a><a href="{{ route('orders.index') }}" class="underline">Orders</a>@endunless<a href="/" class="underline">Account</a></div></nav></header>
    <main id="content" class="mx-auto max-w-5xl px-5 py-10 [overflow-wrap:anywhere] sm:py-14">
        <h1 class="text-3xl font-semibold sm:text-4xl">{{ $title }}</h1>
        <x-commerce-development-banner />
        @if (session('status'))<p role="status" class="mt-6 rounded-xl border border-emerald-800 bg-emerald-950/50 p-4">{{ session('status') }}</p>@endif
        @if ($errors->any())<div role="alert" class="mt-6 rounded-xl border border-rose-800 bg-rose-950/40 p-4"><p>Check the details below.</p><ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if (\App\Http\Middleware\CommercePreviewOnly::enabled())
            <p class="mt-4 text-slate-300">Read-only preview: checkout and purchase changes are unavailable. <a href="/" class="underline">Return to your dashboard</a> to manage projects, services, and support requests.</p>
            <fieldset disabled class="min-w-0">{{ $slot }}</fieldset>
        @else
            {{ $slot }}
        @endif
    </main>
</body>
</html>
