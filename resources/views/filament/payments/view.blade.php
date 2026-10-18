<x-filament-panels::page>
<x-filament::section heading="Captured payment">
<dl class="grid gap-5 md:grid-cols-2">
@foreach(['public_id' => 'Reference', 'state' => 'State', 'provider_session_id' => 'Provider session', 'failure_code' => 'Last verification issue'] as $key => $label)
<div><dt>{{ $label }}</dt><dd class="break-all">{{ $payment['attempt'][$key] ?? 'None' }}</dd></div>
@endforeach
<div><dt>Amount</dt><dd>{{ \App\Services\Carts\MinorUnits::formatPhp($payment['attempt']['amount_minor']) }}</dd></div>
<div><dt>Mode</dt><dd>{{ $payment['attempt']['livemode'] ? 'Live' : 'Test' }}</dd></div>
<div><dt>Order</dt><dd class="break-all">{{ $payment['order']['public_id'] }}</dd></div>
<div><dt>Order payment</dt><dd>{{ $payment['order']['payment_status'] }} · revision {{ $payment['order']['revision'] }}</dd></div>
</dl>
@if($payment['settlement'])<p class="mt-5 break-all" role="status">Verified settlement: {{ $payment['settlement']['public_id'] }} · {{ $payment['settlement']['provider_payment_id'] }}</p>@else<p class="mt-5">No verified settlement. A provider return page does not confirm payment.</p>@endif
</x-filament::section>
<x-filament::section heading="Signed provider receipts">
<p class="mb-5">Original receipt outcomes are retained after reconciliation.</p>
<ol class="space-y-5">@forelse($payment['events'] as $event)<li><p class="break-all font-medium">{{ $event->provider_event_id }}</p><p class="break-words">{{ $event->type }} · {{ $event->state }} {{ $event->reason ? '· '.$event->reason : '' }}</p></li>@empty<li>No signed provider receipts yet.</li>@endforelse</ol>
<nav class="mt-5 flex gap-5" aria-label="Provider receipt pages">@if($payment['pagination']['page'] > 1)<a href="?event_page={{ $payment['pagination']['page'] - 1 }}">Newer</a>@endif @if($payment['pagination']['page'] < $payment['pagination']['last_page'])<a href="?event_page={{ $payment['pagination']['page'] + 1 }}">Older</a>@endif</nav>
</x-filament::section>
<x-filament::section heading="Audited Admin resolutions"><ol class="space-y-5">@forelse($payment['resolutions'] as $resolution)<li><p class="break-all font-medium">{{ $resolution->public_id }} · Admin {{ $resolution->actor_id }} · {{ $resolution->created_at }}</p><p>Original outcome: {{ $resolution->original_state }} · {{ $resolution->original_reason }}</p><p class="mt-2 whitespace-pre-wrap break-words">{{ $resolution->note }}</p></li>@empty<li>No resolutions recorded.</li>@endforelse</ol></x-filament::section>
</x-filament-panels::page>
