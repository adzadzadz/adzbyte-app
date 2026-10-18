<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Save your selections · Adzbyte</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-[#0a0f17] font-sans text-slate-100 antialiased">
    <main class="mx-auto max-w-4xl px-5 py-10 [overflow-wrap:anywhere] sm:py-14">
        <h1 class="text-3xl font-semibold">Save your selections</h1>
        <p class="mt-4 text-slate-300">Combine these selections with your account cart. A service already selected is kept once.</p>
        @if (session('status'))<p role="status" class="mt-6 rounded-xl border border-amber-800 p-4">{{ session('status') }}</p>@endif
        @if ($errors->any())<div role="alert" class="mt-6 rounded-xl border border-rose-800 p-4"><p>Review your selections and try again.</p><ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <div class="mt-8 grid gap-6 sm:grid-cols-2">
            @foreach (['New selections' => $preview['incoming'], 'Account cart' => $preview['saved']] as $label => $cart)
                <section class="min-w-0 rounded-2xl border border-slate-800 bg-slate-900 p-5" aria-label="{{ $label }}">
                    <h2 class="text-xl font-medium">{{ $label }}</h2>
                    @if ($cart === null || $cart['items'] === [])<p class="mt-4 text-slate-400">No services selected.</p>@else
                        <ul class="mt-4 space-y-4">@foreach ($cart['items'] as $line)<li><p class="font-medium">{{ $line['product']['name'] }}</p><p class="mt-1 text-sm text-slate-300">{{ $line['product']['price'] ? \App\Services\Carts\MinorUnits::formatPhp($line['product']['price']['amount_minor']) : 'Price unavailable' }} · {{ $line['available'] ? 'Available' : 'Not currently available' }}</p></li>@endforeach</ul>
                    @endif
                </section>
            @endforeach
        </div>
        <form action="{{ route('cart.attach') }}" method="post" class="mt-8 space-y-6">
            @csrf
            <input type="hidden" name="operation_id" value="{{ \Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="fingerprint" value="{{ $preview['fingerprint'] }}">
            @foreach ($preview['conflicts'] as $conflict)
                <fieldset class="min-w-0 rounded-xl border border-amber-800 p-5">
                    <legend class="px-2 font-medium">{{ $conflict['category'] }} — choose one</legend>
                    <p class="mb-4 text-sm text-slate-300">Keep one service from this category. Your other categories remain selected.</p>
                    @foreach ($conflict['products'] as $product)
                        <label class="mt-3 flex items-start gap-3 rounded-lg border border-slate-700 p-3"><input type="radio" name="choices[{{ $conflict['key'] }}]" value="{{ $product['public_id'] }}" required class="mt-1 shrink-0 accent-violet-500"><span>{{ $product['name'] }}</span></label>
                    @endforeach
                </fieldset>
            @endforeach
            <button type="submit" class="w-full rounded-lg bg-[#8139ff] px-5 py-3 font-medium hover:bg-violet-600 focus-visible:outline-2 focus-visible:outline-offset-4 sm:w-auto">Save selections to my account</button>
        </form>
        <p class="mt-7 text-sm text-slate-400">Current availability, prices and terms are checked again in your saved cart.</p>
        <a href="/" class="mt-5 inline-block underline">Back to account</a>
    </main>
</body>
</html>
