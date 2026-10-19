<x-commerce-layout title="Payment for your request">
    <p class="mt-4 break-words text-slate-300">Request {{ $order->public_id }}</p>
    <p class="mt-5 text-2xl font-semibold">{{ \App\Services\Carts\MinorUnits::formatPhp($order->currentRevision->total_minor) }}</p>
    @if ($payment !== null)
        @if ($payment['state'] === 'paid')
            <p role="status" class="mt-5 text-slate-300">Payment confirmed. Admin will arrange fulfillment of your approved services.</p>
        @elseif ($payment['state'] === 'closing')
            <p role="status" class="mt-5 text-slate-300">This payment request is being closed or verified. Payment is unavailable until its status is confirmed. Contact Admin if you need help.</p>
        @elseif ($payment['checkout_url'] !== null)
            <p class="mt-5 text-slate-300">Continue to PayMongo to pay for your approved services. You can return here to check your payment status.</p>
            <a href="{{ $payment['checkout_url'] }}" rel="noreferrer" class="mt-6 inline-block rounded-xl bg-violet-600 px-5 py-3 font-medium focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-violet-400">Continue to PayMongo</a>
        @else
            <p role="status" class="mt-5 text-slate-300">We are checking this payment request. Please contact Admin before trying again.</p>
        @endif
    @elseif (config('services.paymongo.enabled') && $order->status === 'open' && $order->approval_status === 'approved' && $order->approval_expires_at?->isFuture() && $order->payment_status === 'unpaid')
        <p class="mt-5 text-slate-300">Your request is approved. Continue to secure payment with PayMongo.</p>
        <form method="POST" action="{{ route('orders.payment.store', ['order' => $order->public_id]) }}" class="mt-6">
            @csrf
            <input type="hidden" name="operation_id" value="{{ \Illuminate\Support\Str::uuid() }}">
            <input type="hidden" name="expected_revision" value="{{ $order->revision }}">
            <button type="submit" class="rounded-xl bg-violet-600 px-5 py-3 font-medium focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-violet-400">Prepare secure payment</button>
        </form>
    @else
        <p role="status" class="mt-5 text-slate-300">Payment is currently unavailable for this request. Contact Admin if you need help.</p>
    @endif
    <a href="{{ route('orders.show', ['order' => $order->public_id]) }}" class="mt-7 inline-block underline">Back to your request</a>
</x-commerce-layout>
