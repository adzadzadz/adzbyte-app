@if ($this->commandConflict)
    <section class="adz-portal-callout" role="alert" aria-label="Review a changed record">
        <h3 class="adz-portal-title">This record changed. Your draft is still here.</h3>
        <p>Review the current saved version below before submitting again. Nothing from this attempt was saved.</p>
        <dl class="adz-portal-facts">
            <div><dt>Current record</dt><dd>{{ $this->conflictSnapshot['title'] }}</dd></div>
            <div><dt>Status</dt><dd>{{ \App\Support\PortalOptions::label($this->conflictSnapshot['status'], true) }}</dd></div>
        </dl>
        @foreach ($this->conflictSnapshot['details'] as $label => $value)
            <p><strong>{{ $label }}</strong></p><x-portal-text :text="(string) $value" />
        @endforeach
        <x-filament::button color="gray" wire:click="reviewLatestVersion" wire:loading.attr="disabled">I reviewed this version — keep my draft</x-filament::button>
        <p class="adz-portal-muted">This updates the version used by your draft. Review your entries, then submit again when ready.</p>
    </section>
@endif
