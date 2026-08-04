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
                    Core administration access is ready. Operational queues will appear here as their underlying workflows are connected.
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
