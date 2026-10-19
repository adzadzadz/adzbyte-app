@php
    $offer = $review['proposal'];
    $policy = $offer['policy'] ?? $order['accepted']['policy'];
    $canCorrect = $order['status'] === 'open' && $order['payment_status'] === 'unpaid' && in_array($order['approval_status'], ['clarification', 'expired'], true);
    $value = fn ($group, $field) => is_string($corrections[$group][$field] ?? null) ? $corrections[$group][$field] : ($order['accepted'][$group][$field] ?? '');
@endphp
@if ($order['status'] === 'open' && $order['approval_status'] === 'approved' && in_array($order['payment_status'], ['unpaid', 'pending'], true) && $order['approval_expires_at'])<p class="mt-6 text-slate-300">Approval is valid until {{ $order['approval_expires_at'] }}.</p>@endif
@if ($offer)
<section aria-label="Updated offer" class="mt-7 rounded-xl border border-amber-400 p-5">
    <h2 class="text-xl font-medium">Updated offer for your review</h2><p class="mt-3">Your accepted request stays unchanged until you accept this offer. Admin must approve it again afterward.</p>
    @foreach ($offer['lines'] as $line)<article class="mt-5"><h3 class="font-medium">{{ $line['name'] }} · {{ \App\Services\Carts\MinorUnits::formatPhp($line['amount_minor']) }}</h3><dl class="mt-2 space-y-2">@foreach ($line['terms'] as $key => $text)<div><dt>{{ ucfirst(str_replace('_', ' ', $key)) }}</dt><dd class="whitespace-pre-wrap text-slate-300">{{ is_string($text) ? $text : json_encode($text, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</dd></div>@endforeach</dl></article>@endforeach
    <p class="mt-5">Subtotal {{ \App\Services\Carts\MinorUnits::formatPhp($offer['subtotal_minor']) }} · {{ $policy['tax_mode'] === 'inclusive' ? 'Tax included' : 'Additional tax' }} {{ \App\Services\Carts\MinorUnits::formatPhp($offer['tax_minor']) }}</p><p class="mt-3 text-xl font-semibold">Updated total: {{ \App\Services\Carts\MinorUnits::formatPhp($offer['total_minor']) }}</p>
    <p class="mt-4 whitespace-pre-wrap">{{ $policy['terms'] }}</p><p class="mt-4 whitespace-pre-wrap text-slate-300">{{ $policy['privacy'] }}</p>
    @unless($offer['is_current'])<p role="status" class="mt-4 font-medium">This offer needs to be refreshed by Admin. Send a message below to continue.</p>@endunless
</section>
@endif
@if ($canCorrect && ($offer === null || $offer['is_current']))
<section aria-label="Update your request" class="mt-7 rounded-xl border border-slate-800 p-5">
<h2 class="text-xl font-medium">{{ $offer ? 'Accept the updated offer' : 'Send your corrected request for review' }}</h2>
<form method="POST" action="{{ route($offer ? 'orders.accept_proposal' : 'orders.resubmit', ['order' => $order['public_id']]) }}" class="mt-5 space-y-5">
@csrf<input type="hidden" name="operation_id" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_revision" value="{{ $order['revision'] }}">@if($offer)<input type="hidden" name="proposal" value="{{ $offer['public_id'] }}">@endif
@foreach (['brief' => ['goal' => ['Project goal', 2000], 'scope' => ['Requested scope', 10000], 'website' => ['Website', 2048]], 'billing' => ['name' => ['Billing name', 255], 'email' => ['Billing email', 255], 'country' => ['Country code', 2], 'address' => ['Billing address', 2000], 'company' => ['Company', 255], 'phone' => ['Phone', 50]]] as $group => $fields)
@foreach ($fields as $field => [$label, $limit])
@php($required = in_array($field, $policy['required_'.$group], true))
<label class="block" for="correction-{{ $field }}"><span>{{ $label }}{{ $required ? ' *' : '' }}</span>
@if (in_array($field, ['goal', 'scope', 'address'], true))<textarea id="correction-{{ $field }}" name="{{ $group }}[{{ $field }}]" rows="3" maxlength="{{ $limit }}" @required($required) class="mt-2 w-full rounded-lg border border-slate-600 bg-slate-900 p-3">{{ $value($group, $field) }}</textarea>
@else<input id="correction-{{ $field }}" name="{{ $group }}[{{ $field }}]" type="{{ $field === 'email' ? 'email' : ($field === 'website' ? 'url' : 'text') }}" value="{{ $value($group, $field) }}" maxlength="{{ $limit }}" @required($required) @if($field === 'country') pattern="[A-Z]{2}" @endif class="mt-2 w-full rounded-lg border border-slate-600 bg-slate-900 p-3">@endif
</label>
@endforeach
@endforeach
<label class="flex items-start gap-3"><input type="checkbox" name="accept_terms" value="1" required class="mt-1 h-5 w-5 shrink-0"><span>I accept the {{ $offer ? 'updated' : 'displayed accepted' }} scope, prices and commerce terms, and acknowledge the privacy notice. Send this request to Admin for approval.</span></label>
<button type="submit" class="rounded-lg bg-orange-500 px-5 py-3 font-medium text-slate-950">{{ $offer ? 'Accept offer and send for approval' : 'Resubmit for approval' }}</button>
</form></section>
@endif
<section aria-label="Conversation with Admin" class="mt-7 rounded-xl border border-slate-800 p-5"><h2 class="text-xl font-medium">Conversation with Admin</h2>
<ol class="mt-5 space-y-5">@forelse ($review['messages'] as $message)<li class="border-b border-slate-800 pb-4"><p class="text-sm text-slate-400">{{ $message['sender'] === 'admin' ? 'Admin' : 'You' }} · {{ $message['created_at'] }}</p><p class="mt-2 whitespace-pre-wrap">{{ $message['body'] }}</p></li>@empty<li class="text-slate-400">No messages yet.</li>@endforelse</ol>
<nav aria-label="Conversation pages" class="mt-5 flex flex-wrap gap-5">@if ($review['pagination']['page'] > 1)<a class="underline" href="{{ route('orders.show', ['order' => $order['public_id'], 'page' => $review['pagination']['page'] - 1]) }}">Newer messages</a>@endif @if ($review['pagination']['page'] < $review['pagination']['last_page'])<a class="underline" href="{{ route('orders.show', ['order' => $order['public_id'], 'page' => $review['pagination']['page'] + 1]) }}">Older messages</a>@endif</nav>
@if ($order['status'] === 'open' && $order['payment_status'] === 'unpaid' && $order['approval_status'] !== 'rejected')
<form method="POST" action="{{ route('orders.reply', ['order' => $order['public_id']]) }}" class="mt-6 space-y-4">@csrf<input type="hidden" name="operation_id" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_revision" value="{{ $order['revision'] }}"><label for="customer-message" class="block">Your message</label><textarea id="customer-message" name="message" required maxlength="10000" rows="4" class="w-full rounded-lg border border-slate-600 bg-slate-900 p-3">{{ is_string($corrections['message'] ?? null) ? $corrections['message'] : '' }}</textarea><button type="submit" class="rounded-lg bg-orange-500 px-5 py-3 font-medium text-slate-950">Send message</button><p class="text-sm text-slate-400">A message does not resubmit or accept an offer.</p></form>
@endif
</section>
