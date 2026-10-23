<x-commerce-layout title="Review your request">
    <p class="mt-4 text-slate-300">Tell us what you need. Admin will review your request and may contact you to clarify the scope before approving it. No payment is requested yet.</p>
    @if (! $review['cart']['selection_valid'])<p role="alert" class="mt-6 rounded-xl border border-amber-800 p-4">Your cart needs review. <a href="{{ route('cart.show') }}" class="underline">Return to your cart</a> to confirm changed prices and terms, remove unavailable services, or resolve category choices.</p>@endif
    <section aria-label="Services for review" class="mt-8 grid gap-5 sm:grid-cols-2">
        @forelse ($review['cart']['items'] as $line)
            <article class="min-w-0 rounded-2xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="text-xl font-medium">{{ $line['product']['name'] }}</h2>
                <p class="mt-3">{{ $line['product']['price'] ? \App\Services\Carts\MinorUnits::formatPhp($line['product']['price']['amount_minor']) : 'Price unavailable' }} · Quantity 1</p>
                <p class="mt-2 text-sm text-slate-400">{{ $line['available'] ? 'Available' : 'Not currently available' }}</p>
                <details class="mt-4"><summary class="cursor-pointer underline">Service scope and terms</summary><dl class="mt-4 space-y-3">@foreach ((array) ($line['product']['terms']['content'] ?? []) as $key => $value)<div><dt class="font-medium">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt><dd class="mt-1 whitespace-pre-wrap text-slate-300">{{ is_string($value) ? $value : json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</dd></div>@endforeach</dl></details>
            </article>
        @empty<p>No services selected. <a href="{{ route('cart.show') }}" class="underline">Return to your cart</a>.</p>@endforelse
    </section>
    <section aria-label="Request totals" class="mt-6 rounded-xl border border-slate-800 bg-slate-900 p-5">
        <dl class="space-y-3"><div class="flex flex-wrap justify-between gap-3"><dt>Service subtotal</dt><dd>{{ \App\Services\Carts\MinorUnits::formatPhp($review['totals']['subtotal_minor']) }}</dd></div><div class="flex flex-wrap justify-between gap-3"><dt>{{ $review['policy']['tax_mode'] === 'inclusive' ? 'Tax included' : 'Additional tax' }}</dt><dd>{{ \App\Services\Carts\MinorUnits::formatPhp($review['totals']['tax_minor']) }}</dd></div><div class="flex flex-wrap justify-between gap-3 border-t border-slate-700 pt-3 text-xl font-semibold"><dt>Total for review</dt><dd>{{ \App\Services\Carts\MinorUnits::formatPhp($review['totals']['total_minor']) }}</dd></div></dl>
        <p class="mt-4 text-sm text-slate-400">This is an order summary. Payment becomes available after the required review and approval.</p>
    </section>
    <form method="post" action="{{ route('checkout.store') }}" class="mt-9 space-y-8">
        @csrf
        <input type="hidden" name="operation_id" value="{{ $draft['operation_id'] }}">
        <input type="hidden" name="fingerprint" value="{{ $review['fingerprint'] }}">
        @foreach (['brief' => ['Project brief', ['goal' => 'What would you like to achieve?', 'scope' => 'What help do you need?', 'website' => 'Website']], 'billing' => ['Billing details', ['name' => 'Billing name', 'email' => 'Billing email', 'country' => 'Country code (2 letters)', 'address' => 'Billing address', 'company' => 'Company', 'phone' => 'Phone']]] as $group => [$legend, $fields])
            <fieldset class="min-w-0 rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6"><legend class="px-2 text-xl font-medium">{{ $legend }}</legend><div class="grid gap-6 sm:grid-cols-2">
            @foreach ($fields as $field => $label)
                @php($oldValue = $draft[$group][$field] ?? null)
                @php($required = in_array($field, $review['policy']['required_'.$group], true))
                <div class="min-w-0 {{ in_array($field, ['goal', 'scope', 'address'], true) ? 'sm:col-span-2' : '' }}">
                    <label for="{{ $group }}-{{ $field }}" class="block font-medium">{{ $label }}{{ $required ? ' (required)' : ' (optional)' }}</label>
                    @if (in_array($field, ['goal', 'scope', 'address'], true))
                    <textarea id="{{ $group }}-{{ $field }}" name="{{ $group }}[{{ $field }}]" rows="{{ $field === 'scope' ? 5 : 3 }}" maxlength="{{ $field === 'scope' ? 10000 : 2000 }}" @required($required) class="mt-2 w-full min-w-0 rounded-lg border border-slate-600 bg-[#0a0f17] px-4 py-3 focus-visible:outline-2 focus-visible:outline-offset-4">{{ is_string($oldValue) ? $oldValue : '' }}</textarea>
                    @else
                    <input id="{{ $group }}-{{ $field }}" name="{{ $group }}[{{ $field }}]" type="{{ $field === 'email' ? 'email' : ($field === 'website' ? 'url' : ($field === 'phone' ? 'tel' : 'text')) }}" maxlength="{{ $field === 'country' ? 2 : ($field === 'website' ? 2048 : ($field === 'phone' ? 50 : 255)) }}" @if ($field === 'country') pattern="[A-Z]{2}" placeholder="e.g. PH" autocomplete="country" @elseif ($field === 'email') autocomplete="email" @elseif ($field === 'name') autocomplete="name" @endif value="{{ is_string($oldValue) ? $oldValue : '' }}" @required($required) class="mt-2 w-full min-w-0 rounded-lg border border-slate-600 bg-[#0a0f17] px-4 py-3 focus-visible:outline-2 focus-visible:outline-offset-4">
                    @endif
                </div>
            @endforeach
            </div></fieldset>
        @endforeach
        <section aria-label="Commerce terms and privacy" class="min-w-0 rounded-2xl border border-slate-800 p-5 sm:p-6">
            <h2 class="text-xl font-medium">Terms for this request</h2><p class="mt-3 text-sm text-slate-400">Version {{ $review['policy']['version'] }}</p>
            <p class="mt-4 whitespace-pre-wrap text-slate-300">{{ $review['policy']['terms'] }}</p>
            <h2 class="mt-6 text-xl font-medium">Privacy notice</h2><p class="mt-4 whitespace-pre-wrap text-slate-300">{{ $review['policy']['privacy'] }}</p>
            <p class="mt-4 text-sm text-slate-400">Unsubmitted briefs: up to {{ $review['policy']['retention']['draft_days'] }} days after inactivity. Closed unsuccessful requests: {{ $review['policy']['retention']['closed_request_days'] }} days. Required financial records: {{ $review['policy']['retention']['financial_years'] }} years after the applicable filing date, or longer under a legal hold. Cart items do not expire by time.</p>
            <label class="mt-6 flex items-start gap-3"><input type="checkbox" name="accept_terms" value="1" required class="mt-1 shrink-0 accent-violet-500"><span>I accept the displayed service scope, prices and terms for this request.</span></label>
        </section>
        <button type="submit" @disabled(! $review['cart']['selection_valid']) class="w-full rounded-lg bg-[#8139ff] px-5 py-3 font-medium focus-visible:outline-2 focus-visible:outline-offset-4 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">Send request for review</button>
    </form>
</x-commerce-layout>
