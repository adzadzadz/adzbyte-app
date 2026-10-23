@php
    $user = filament()->auth()->user();
    $firstName = str($user->name)->trim()->before(' ')->toString() ?: 'there';
@endphp
<x-filament-panels::page>
    <div class="adz-portal adz-customer-home" data-customer-home>
        <section class="adz-portal-hero" aria-labelledby="customer-home-heading">
            <p class="adz-portal-kicker">Your Adzbyte Solutions portal</p>
            <h2 id="customer-home-heading" class="adz-portal-heading">Welcome back, {{ $firstName }}.</h2>
            <p class="adz-portal-intro">Follow your projects and services, see what needs your attention, and stay in touch with your team.</p>
            <div class="adz-portal-actions"><x-filament::button tag="a" :href="\App\Filament\Account\Pages\Projects::getUrl(panel: 'account')" icon="heroicon-o-rectangle-stack">View projects</x-filament::button><x-filament::button tag="a" color="gray" :href="\App\Filament\Account\Pages\Support::getUrl(panel: 'account')" icon="heroicon-o-chat-bubble-left-right">Contact your team</x-filament::button></div>
        </section>
        <div class="adz-portal-stats" aria-label="Your workspace summary">
            @foreach ([['Active projects', $overview['projects'], \App\Filament\Account\Pages\Projects::getUrl(panel: 'account')], ['Active services', $overview['services'], \App\Filament\Account\Pages\Services::getUrl(panel: 'account')], ['Waiting for you', $overview['waiting'], null], ['Open conversations', $overview['open_cases'], \App\Filament\Account\Pages\Support::getUrl(panel: 'account')]] as [$label, $count, $url])
                <div class="adz-portal-stat"><span class="adz-portal-stat-number">{{ $count }}</span>@if ($url)<a class="adz-portal-link" href="{{ $url }}">{{ $label }}</a>@else<span>{{ $label }}</span>@endif</div>
            @endforeach
        </div>
        @if ($overview['waiting'])<p class="adz-portal-callout" role="note">Your team is waiting for your input. Look for <strong>Waiting for you</strong> in your projects, services and conversations below.</p>@endif
        <section aria-labelledby="recent-work-heading" class="adz-portal-section"><div class="adz-portal-row"><h2 id="recent-work-heading" class="adz-portal-section-title">Your projects & services</h2><a class="adz-portal-link" href="{{ \App\Filament\Account\Pages\Services::getUrl(panel: 'account') }}">All services →</a></div><div class="adz-portal-grid">@forelse (array_slice($overview['works'], 0, 4) as $item)@include('filament.portal.work-card', ['item' => $item, 'staff' => false])@empty<section class="adz-portal-card adz-portal-empty"><h3 class="adz-portal-title">Your workspace is ready</h3><p class="adz-portal-muted">Your team will add your projects and services here. Each one will show progress, milestones, updates and the next step.</p><a class="adz-portal-link" href="{{ \App\Filament\Account\Pages\Support::getUrl(panel: 'account') }}">Start a conversation with your team</a></section>@endforelse</div></section>
        <livewire:portal.support-inbox :key="'dashboard-support'" />
        <div class="adz-portal-grid">
            <section class="adz-portal-card" aria-labelledby="account-summary-heading"><p class="adz-portal-kicker">Account</p><h2 id="account-summary-heading" class="adz-portal-title">Your details</h2><dl class="adz-portal-facts"><div><dt>Name</dt><dd>{{ $user->name }}</dd></div><div><dt>Email</dt><dd>{{ $user->email }}</dd></div></dl><p class="adz-portal-verified">✓ Email verified</p><a class="adz-portal-link" href="{{ filament()->getProfileUrl() }}">Manage profile & security</a></section>
            <section class="adz-portal-card"><span class="adz-portal-status" data-status="on_hold">Under development</span><h2 class="adz-portal-title">Ecommerce is taking shape</h2><p class="adz-portal-muted">See what we’re building for carts, orders and payments. Purchasing is still under development.</p><a class="adz-portal-link" href="{{ \App\Filament\Account\Pages\CommercePreview::getUrl(panel: 'account') }}">Explore the preview →</a></section>
        </div>
    </div>
</x-filament-panels::page>
