<section class="adz-portal-section" @if ($contextWork) id="work-conversations" @endif aria-label="Requests and reports">
    <div class="adz-portal-row"><h2 class="adz-portal-section-title">Requests & reports</h2><div class="adz-portal-actions">@foreach ($this->getCachedHeaderActions() as $action){{ $action }}@endforeach</div></div>
    <p class="adz-portal-muted">{{ $contextWork ? 'Conversations about this project or service.' : 'All conversations, including general requests and linked work.' }} Expand a row to read and reply here.</p>
    @include('filament.portal.inbox')
    <x-filament-actions::modals />
</section>
