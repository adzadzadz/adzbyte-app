<x-filament-panels::page>
@php($order = $review['order'])
<x-filament::section heading="Accepted request">
<p class="mb-5 break-all">Reference {{ $order['public_id'] }}</p>
<dl class="grid gap-5 md:grid-cols-2"><div><dt>Approval</dt><dd>{{ ucfirst($order['approval_status']) }}</dd></div><div><dt>Payment</dt><dd>{{ ucfirst($order['payment_status']) }}</dd></div><div><dt>Revision</dt><dd>{{ $order['revision'] }}</dd></div><div><dt>Approval valid until</dt><dd>{{ $order['approval_expires_at'] ?? 'Not approved' }}</dd></div></dl>
<p class="mt-5 text-xl font-semibold">Accepted total: {{ \App\Services\Carts\MinorUnits::formatPhp($order['accepted']['total_minor']) }}</p>
@foreach ($order['accepted']['lines'] as $line)<div class="mt-5"><h3 class="font-medium">{{ $line['name'] }} · {{ \App\Services\Carts\MinorUnits::formatPhp($line['amount_minor']) }}</h3><pre class="mt-2 whitespace-pre-wrap break-words">{{ json_encode($line['terms'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>@endforeach
@foreach (['brief' => 'Project brief', 'billing' => 'Billing details'] as $group => $label)<h3 class="mt-6 font-semibold">{{ $label }}</h3><dl class="mt-3 space-y-3">@foreach ($order['accepted'][$group] as $key => $value)<div><dt>{{ ucfirst($key) }}</dt><dd class="whitespace-pre-wrap break-words">{{ $value }}</dd></div>@endforeach</dl>@endforeach
<details class="mt-5"><summary>Accepted commerce terms and privacy notice (version {{ $order['accepted']['policy']['version'] }})</summary><p class="mt-3 whitespace-pre-wrap">{{ $order['accepted']['policy']['terms'] }}</p><p class="mt-3 whitespace-pre-wrap">{{ $order['accepted']['policy']['privacy'] }}</p></details>
</x-filament::section>
@if($review['proposal'])<x-filament::section heading="Pending updated offer"><p>{{ $review['proposal']['is_current'] ? 'Waiting for customer acceptance.' : 'Offer changed. Send an updated offer before customer acceptance.' }}</p><p class="mt-3">Proposed total: {{ \App\Services\Carts\MinorUnits::formatPhp($review['proposal']['total_minor']) }}</p></x-filament::section>@endif
<x-filament::section heading="Conversation and internal notes"><ol class="space-y-5">@forelse($review['messages'] as $message)<li><p class="font-medium">{{ $message['audience'] === 'internal' ? 'Internal note · Admin only' : ($message['sender'] === 'admin' ? 'Admin to customer' : 'Customer') }} · {{ $message['created_at'] }}</p><p class="mt-2 whitespace-pre-wrap break-words">{{ $message['body'] }}</p></li>@empty<li>No messages yet.</li>@endforelse</ol>
<nav class="mt-5 flex gap-5" aria-label="Conversation pages">@if($review['pagination']['page'] > 1)<a href="?page={{ $review['pagination']['page'] - 1 }}">Newer</a>@endif @if($review['pagination']['page'] < $review['pagination']['last_page'])<a href="?page={{ $review['pagination']['page'] + 1 }}">Older</a>@endif</nav>
</x-filament::section>
</x-filament-panels::page>
