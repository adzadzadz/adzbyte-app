<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Your cart · Adzbyte</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-[#0a0f17] font-sans text-slate-100 antialiased">
    <a href="#cart-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:bg-slate-800 focus:p-3">Skip to cart</a>
    <header class="border-b border-slate-800 px-5 py-5">
        <nav class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-5" aria-label="Cart navigation">
            <a href="{{ $shop }}" aria-label="Adzbyte services"><img src="{{ asset('images/brand/adzbyte-logo-transparent.png') }}" width="160" height="45" alt="Adzbyte" class="h-auto w-36"></a>
            <a href="{{ $shop }}" class="underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-4">Browse services</a>
        </nav>
    </header>
    <main id="cart-content" class="mx-auto max-w-5xl px-5 py-10 [overflow-wrap:anywhere] sm:py-14">
        <h1 class="text-3xl font-semibold sm:text-4xl">Your cart</h1>
        <p class="mt-3 text-slate-400">Availability and current prices are checked again when you review your cart.</p>
        @if (session('status'))
            <p role="status" class="mt-6 rounded-xl border border-emerald-800 bg-emerald-950/50 p-4">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <div role="alert" class="mt-6 rounded-xl border border-rose-800 bg-rose-950/50 p-4">
                <p>Review your cart and try again.</p>
                <ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @if ($cart === null)
            <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900 p-6">
                <h2 class="text-xl font-semibold">No cart is open</h2>
                <p class="mt-3 text-slate-300">Return to the website and select View Cart to open your saved selections.</p>
                <a href="{{ $shop }}" class="mt-5 inline-block underline">Browse services</a>
            </section>
        @elseif ($cart['items'] === [])
            <section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900 p-6">
                <h2 class="text-xl font-semibold">Your cart is empty</h2>
                <a href="{{ $shop }}" class="mt-5 inline-block underline">Choose a service</a>
            </section>
        @else
            @if (! $cart['selection_valid'])
                <p role="status" class="mt-6 rounded-xl border border-amber-800 bg-amber-950/40 p-4">Some selections need review. Remove unavailable services and resolve category conflicts before continuing.</p>
            @endif
            <div class="mt-8 grid items-start gap-7 lg:grid-cols-[minmax(0,1fr)_19rem]">
                <section aria-label="Selected services" class="min-w-0 space-y-5">
                    @foreach ($cart['items'] as $item)
                        @php($product = $item['product'])
                        <article class="min-w-0 rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6">
                            <h2 class="text-xl font-semibold">{{ $product['name'] }}</h2>
                            @if ($product['category'])<p class="mt-2 text-sm text-slate-400">{{ $product['category']['name'] }}</p>@endif
                            <p class="mt-4 text-lg font-semibold">{{ $product['price'] ? \App\Services\Carts\MinorUnits::formatPhp($product['price']['amount_minor']) : 'Price unavailable' }}</p>
                            <p class="mt-2 text-sm text-slate-300">{{ $item['available'] ? 'Available · Quantity 1' : 'Not currently available' }}</p>
                            @if ($item['requires_review'])<p class="mt-3 text-amber-300">Prices or terms changed. Review the current details.</p>@endif
                            @if ($item['category_conflict'])<p class="mt-3 text-amber-300">Keep one service from this category and remove the others.</p>@endif
                            @php($content = (array) ($product['terms']['content'] ?? []))
                            @if ($content !== [])
                                <details class="mt-5">
                                    <summary class="cursor-pointer underline underline-offset-4">Service details and terms</summary>
                                    <dl class="mt-4 space-y-4">
                                        @foreach (['scope' => 'Scope', 'deliverables' => 'Deliverables', 'exclusions' => 'Exclusions', 'customer_obligations' => 'Customer obligations', 'fulfillment' => 'Fulfillment', 'entitlements' => 'Included access and limits'] as $key => $label)
                                            @if (isset($content[$key]))<div><dt class="font-medium">{{ $label }}</dt><dd class="mt-1 whitespace-pre-wrap text-slate-300">{{ $content[$key] }}</dd></div>@endif
                                        @endforeach
                                    </dl>
                                </details>
                            @endif
                            <form method="post" action="{{ route('cart.remove', ['product' => $product['public_id']]) }}" class="mt-5">
                                @csrf @method('DELETE')
                                <input type="hidden" name="operation_id" value="{{ \Illuminate\Support\Str::uuid() }}">
                                <button type="submit" class="rounded-lg border border-slate-600 px-4 py-2 text-sm hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-4" aria-label="Remove {{ $product['name'] }}">Remove</button>
                            </form>
                        </article>
                    @endforeach
                </section>
                <section aria-label="Cart subtotal" class="min-w-0 rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6">
                    <h2 class="text-lg font-medium">Subtotal</h2>
                    <p class="mt-3 text-2xl font-semibold">{{ \App\Services\Carts\MinorUnits::formatPhp($cart['subtotal_minor']) }}</p>
                    @if (! $cart['subtotal_complete'])<p class="mt-3 text-sm text-amber-300">Unavailable listings are excluded from this subtotal.</p>@endif
                    @if ($cart['requires_review'] && $cart['category_conflicts'] === [] && collect($cart['items'])->every(fn ($line) => $line['available']))
                        <form method="post" action="{{ route('cart.confirm') }}" class="mt-6">
                            @csrf
                            <input type="hidden" name="operation_id" value="{{ \Illuminate\Support\Str::uuid() }}">
                            @foreach ($cart['items'] as $line)
                                <input type="hidden" name="versions[{{ $line['product']['public_id'] }}][price]" value="{{ $line['product']['price']['version'] }}">
                                <input type="hidden" name="versions[{{ $line['product']['public_id'] }}][terms]" value="{{ $line['product']['terms']['version'] }}">
                            @endforeach
                            <button type="submit" class="w-full rounded-lg bg-[#8139ff] px-4 py-3 font-medium hover:bg-violet-600 focus-visible:outline-2 focus-visible:outline-offset-4">Confirm reviewed prices and terms</button>
                        </form>
                    @elseif ($cart['selection_valid'])
                        <a href="{{ route('filament.account.auth.login') }}" class="mt-6 block rounded-lg bg-[#8139ff] px-4 py-3 text-center font-medium hover:bg-violet-600 focus-visible:outline-2 focus-visible:outline-offset-4">Sign in to continue</a>
                    @endif
                    <p class="mt-5 text-sm text-slate-400">Review your selections before continuing.</p>
                </section>
            </div>
        @endif
    </main>
</body>
</html>
