@if (\App\Http\Middleware\CommercePreviewOnly::enabled())
    <aside role="status" class="mt-6 rounded-xl border border-amber-700 bg-amber-950/40 p-5 text-amber-100">
        <h2 class="font-semibold">Under development</h2>
        <p class="mt-2 text-sm leading-6">You can explore these screens while we build ecommerce. Purchasing and payment actions are not available yet. Your projects, services and support conversations are ready in your portal.</p>
        <a href="/" class="mt-3 inline-block underline">Back to your portal</a>
    </aside>
@endif
