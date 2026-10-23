<x-filament-panels::page>
    <p class="adz-portal-intro">{{ $staff ? 'Triage requests and reports, respond to customers, and keep internal notes private.' : 'All your conversations, including general requests and those linked to projects or services. Expand a row to read and reply.' }}</p>
    @include('filament.portal.inbox')
</x-filament-panels::page>
