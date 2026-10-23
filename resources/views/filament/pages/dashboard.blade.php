@php
    $user = filament()->auth()->user();
    $firstName = str($user->name)->trim()->before(' ')->toString();
    $firstName = $firstName !== '' ? $firstName : 'there';
    $roles = $user->getRoleNames()
        ->map(fn (string $role): string => str($role)->replace('_', ' ')->title()->toString());
@endphp

<x-filament-panels::page>
    <div class="adz-admin-home" data-admin-home>
        <section class="adz-admin-home__hero" aria-labelledby="admin-home-heading">
            <div>
                <p class="adz-admin-home__eyebrow">Operations workspace</p>
                <h2 id="admin-home-heading" class="adz-admin-home__heading">Welcome back, {{ $firstName }}.</h2>
                <p class="adz-admin-home__intro">
                    Manage your customer workspace, keep project progress current, and follow up on support conversations.
                </p>
            </div>

            @if ($profileUrl = filament()->getProfileUrl())
                <x-filament::button
                    tag="a"
                    :href="$profileUrl"
                    icon="heroicon-o-user-circle"
                    size="sm"
                >
                    Profile
                </x-filament::button>
            @endif
        </section>

        @if ($portalOverview['work'] || $portalOverview['support'])
            <div class="adz-portal-grid" aria-label="Customer portal operations">
                @if ($portalOverview['work'])<section class="adz-portal-card"><p class="adz-portal-kicker">Projects & services</p><h2 class="adz-portal-title">{{ $portalOverview['work']['active'] }} active work records</h2><p class="adz-portal-muted">{{ $portalOverview['work']['waiting'] }} waiting for customer input.</p><a wire:navigate class="adz-portal-link" href="{{ \App\Filament\Pages\Portal\ClientWork::getUrl(panel: 'admin') }}">Manage customer work →</a></section>@endif
                @if ($portalOverview['support'])<section class="adz-portal-card"><p class="adz-portal-kicker">Customer support</p><h2 class="adz-portal-title">{{ $portalOverview['support']['open'] }} open conversations</h2><p class="adz-portal-muted">{{ $portalOverview['support']['unassigned'] }} need a staff assignment.</p><a wire:navigate class="adz-portal-link" href="{{ \App\Filament\Pages\Portal\CustomerSupport::getUrl(panel: 'admin') }}">Open support queue →</a></section>@endif
            </div>
        @endif

        @if ($portalOverview['customers'])
            <section class="adz-portal-card"><p class="adz-portal-kicker">Customer management</p><h2 class="adz-portal-title">{{ $portalOverview['customers']['total'] }} customers · {{ $portalOverview['customers']['pending'] }} awaiting activation</h2><p class="adz-portal-muted">Start with a customer account, then assign their project or service. Send an activation invitation so they can choose a password.</p><a wire:navigate class="adz-portal-link" href="{{ \App\Filament\Pages\Portal\Customers::getUrl(panel: 'admin') }}">Manage customers →</a></section>
        @endif
        @if ($portalOverview['recent_cases'])
            <section class="adz-portal-section"><h2 class="adz-portal-section-title">Support follow-up</h2><div class="adz-portal-case-list">@foreach ($portalOverview['recent_cases'] as $item)@include('filament.portal.case-card', ['item' => $item, 'staff' => true])@endforeach</div></section>
        @endif
        <section class="adz-portal-card"><h2 class="adz-portal-title">Daily workflow</h2><ol class="adz-portal-history"><li>Create the customer account and queue their activation invitation.</li><li>Assign a project or service with its next step and target date.</li><li>Keep milestones and progress updates current.</li><li>Open linked conversations from the work page, or use Customer support for the full queue.</li></ol></section>
    </div>
</x-filament-panels::page>
