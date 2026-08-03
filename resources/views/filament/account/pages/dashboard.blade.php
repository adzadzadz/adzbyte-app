@php
    $user = filament()->auth()->user();
    $firstName = str($user->name)->trim()->before(' ')->toString();
    $firstName = $firstName !== '' ? $firstName : 'there';
@endphp

<x-filament-panels::page>
    <div class="adz-customer-home" data-customer-home>
        <section class="adz-customer-home__hero" aria-labelledby="customer-home-heading">
            <div class="adz-customer-home__hero-copy">
                <p class="adz-customer-home__eyebrow">Your Adzbyte workspace</p>
                <h2 id="customer-home-heading" class="adz-customer-home__heading">
                    Welcome back, {{ $firstName }}.
                </h2>
                <p class="adz-customer-home__intro">
                    This is your secure home for account details and future project activity with Adzbyte.
                </p>
            </div>

            @if ($profileUrl = filament()->getProfileUrl())
                <x-filament::button
                    tag="a"
                    :href="$profileUrl"
                    icon="heroicon-o-user-circle"
                    class="adz-customer-home__primary-action"
                >
                    Manage profile
                </x-filament::button>
            @endif
        </section>

        <div class="adz-customer-home__grid">
            <section class="adz-customer-home__card" aria-labelledby="account-summary-heading">
                <div class="adz-customer-home__card-header">
                    <span class="adz-customer-home__icon" aria-hidden="true">
                        <x-filament::icon icon="heroicon-o-user-circle" />
                    </span>
                    <div>
                        <p class="adz-customer-home__card-kicker">Account</p>
                        <h3 id="account-summary-heading" class="adz-customer-home__card-title">Your details</h3>
                    </div>
                </div>

                <dl class="adz-customer-home__details">
                    <div>
                        <dt>Name</dt>
                        <dd>{{ $user->name }}</dd>
                    </div>
                    <div>
                        <dt>Email</dt>
                        <dd>{{ $user->email }}</dd>
                    </div>
                </dl>

                <div class="adz-customer-home__verified">
                    <x-filament::icon icon="heroicon-o-check-badge" aria-hidden="true" />
                    <span>Email verified</span>
                </div>
            </section>

            <section class="adz-customer-home__card adz-customer-home__empty" aria-labelledby="workspace-status-heading">
                <img
                    src="{{ asset('images/brand/adzbyte-logo-square-dark.webp') }}"
                    alt=""
                    width="500"
                    height="500"
                    class="adz-customer-home__mark"
                >
                <div>
                    <p class="adz-customer-home__card-kicker">Workspace</p>
                    <h3 id="workspace-status-heading" class="adz-customer-home__card-title">Your workspace is ready</h3>
                    <p class="adz-customer-home__empty-copy">
                        Purchases and project activity will appear here as each core workflow is connected.
                    </p>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
