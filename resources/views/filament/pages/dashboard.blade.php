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
                @if ($portalOverview['work'])<section class="adz-portal-card"><p class="adz-portal-kicker">Projects & services</p><h2 class="adz-portal-title">{{ $portalOverview['work']['active'] }} active work records</h2><p class="adz-portal-muted">{{ $portalOverview['work']['waiting'] }} waiting for customer input.</p><a class="adz-portal-link" href="{{ \App\Filament\Pages\Portal\ClientWork::getUrl(panel: 'admin') }}">Manage customer work →</a></section>@endif
                @if ($portalOverview['support'])<section class="adz-portal-card"><p class="adz-portal-kicker">Customer support</p><h2 class="adz-portal-title">{{ $portalOverview['support']['open'] }} open conversations</h2><p class="adz-portal-muted">{{ $portalOverview['support']['unassigned'] }} need a staff assignment.</p><a class="adz-portal-link" href="{{ \App\Filament\Pages\Portal\CustomerSupport::getUrl(panel: 'admin') }}">Open support queue →</a></section>@endif
            </div>
        @endif

        <div class="adz-admin-home__grid">
            <section class="adz-admin-home__card" aria-labelledby="admin-access-heading">
                <div class="adz-admin-home__card-header">
                    <span class="adz-admin-home__icon" aria-hidden="true">
                        <x-filament::icon icon="heroicon-o-shield-check" />
                    </span>
                    <div>
                        <p class="adz-admin-home__card-kicker">Current session</p>
                        <h3 id="admin-access-heading" class="adz-admin-home__card-title">Access context</h3>
                    </div>
                </div>

                <dl class="adz-admin-home__details">
                    <div>
                        <dt>Administrator</dt>
                        <dd>{{ $user->name }}</dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd>{{ $user->email }}</dd>
                    </div>
                    <div>
                        <dt>Assigned roles</dt>
                        <dd>{{ $roles->join(', ') }}</dd>
                    </div>
                </dl>
            </section>

            <section class="adz-admin-home__card" aria-labelledby="admin-foundation-heading">
                <div class="adz-admin-home__card-header">
                    <span class="adz-admin-home__icon adz-admin-home__icon--cyan" aria-hidden="true">
                        <x-filament::icon icon="heroicon-o-command-line" />
                    </span>
                    <div>
                        <p class="adz-admin-home__card-kicker">Configuration</p>
                        <h3 id="admin-foundation-heading" class="adz-admin-home__card-title">Core safeguards</h3>
                    </div>
                </div>

                <ul class="adz-admin-home__safeguards" aria-label="Configured core safeguards">
                    <li>
                        <span>Authenticated panel</span>
                        <span class="adz-admin-home__state">Active</span>
                    </li>
                    <li>
                        <span>Email verification</span>
                        <span class="adz-admin-home__state">Required</span>
                    </li>
                    <li>
                        <span>Role-based access</span>
                        <span class="adz-admin-home__state">Enforced</span>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</x-filament-panels::page>
