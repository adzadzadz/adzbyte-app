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
            @foreach ([['Active projects', $overview['projects'], \App\Filament\Account\Pages\Projects::getUrl(panel: 'account')], ['Active services', $overview['services'], \App\Filament\Account\Pages\Services::getUrl(panel: 'account')], ['Waiting for you', $overview['waiting'], '#needs-attention'], ['Open conversations', $overview['open_cases'], \App\Filament\Account\Pages\Support::getUrl(panel: 'account')]] as [$label, $count, $url])
                <div class="adz-portal-stat"><span class="adz-portal-stat-number">{{ $count }}</span>@if ($url)<a @if (! str_starts_with($url, '#')) wire:navigate @endif class="adz-portal-link" href="{{ $url }}">{{ $label }}</a>@else<span>{{ $label }}</span>@endif</div>
            @endforeach
        </div>
        <section id="needs-attention" class="adz-portal-card adz-portal-attention" aria-labelledby="attention-heading">
            <h2 id="attention-heading" class="adz-portal-title">Needs your attention</h2>
            <p class="adz-portal-muted">{{ $overview['waiting'] ? 'Open a filtered list to see every item waiting for your input.' : 'There is nothing waiting for your input right now.' }}</p>
            <div class="adz-portal-actions">
                <a wire:navigate class="adz-portal-link" href="{{ \App\Filament\Account\Pages\Projects::getUrl(['queueFilter' => 'waiting'], panel: 'account') }}">Projects waiting for you</a>
                <a wire:navigate class="adz-portal-link" href="{{ \App\Filament\Account\Pages\Services::getUrl(['queueFilter' => 'waiting'], panel: 'account') }}">Services waiting for you</a>
                <a wire:navigate class="adz-portal-link" href="{{ \App\Filament\Account\Pages\Support::getUrl(['queueFilter' => 'waiting'], panel: 'account') }}">Conversations waiting for you</a>
            </div>
        </section>
        @if ($overview['waiting'])<p class="adz-portal-callout" role="note">Your team is waiting for your input. Look for <strong>Waiting for you</strong> in your projects, services and conversations below.</p>@endif
        <section aria-labelledby="recent-work-heading" class="adz-portal-section"><div class="adz-portal-row"><h2 id="recent-work-heading" class="adz-portal-section-title">Your projects & services</h2><a wire:navigate class="adz-portal-link" href="{{ \App\Filament\Account\Pages\Services::getUrl(panel: 'account') }}">All services →</a></div><div class="adz-portal-grid">@forelse (array_slice($overview['works'], 0, 4) as $item)@include('filament.portal.work-card', ['item' => $item, 'staff' => false])@empty<section class="adz-portal-card adz-portal-empty"><h3 class="adz-portal-title">Your workspace is ready</h3><p class="adz-portal-muted">Your team will add your projects and services here. Each one will show progress, milestones, updates and the next step.</p><a wire:navigate class="adz-portal-link" href="{{ \App\Filament\Account\Pages\Support::getUrl(panel: 'account') }}">Start a conversation with your team</a></section>@endforelse</div></section>
        <livewire:portal.support-inbox :key="'dashboard-support'" />
        <div class="adz-portal-grid">
            <section class="adz-portal-card" aria-labelledby="account-summary-heading"><p class="adz-portal-kicker">Account</p><h2 id="account-summary-heading" class="adz-portal-title">Your details</h2><dl class="adz-portal-facts"><div><dt>Name</dt><dd>{{ $user->name }}</dd></div><div><dt>Email</dt><dd>{{ $user->email }}</dd></div></dl><p class="adz-portal-verified">✓ Email verified</p><a wire:navigate class="adz-portal-link" href="{{ filament()->getProfileUrl() }}">Manage profile & security</a></section>
            <section class="adz-portal-card"><span class="adz-portal-status" data-status="on_hold">Under development</span><h2 class="adz-portal-title">Ecommerce is taking shape</h2><p class="adz-portal-muted">Carts, orders and payments are under development.</p>@if (\App\Support\Filament\CommerceNavigation::locked())<button type="button" class="adz-portal-link" aria-disabled="true" x-data="{}" x-on:click="$tooltip(@js(\App\Support\Filament\CommerceNavigation::MESSAGE), { theme: $store.theme })"><x-filament::icon icon="heroicon-o-lock-closed" class="inline-block h-4 w-4" /> Commerce locked</button>@else<a wire:navigate class="adz-portal-link" href="{{ \App\Filament\Account\Pages\CommercePreview::getUrl(panel: 'account') }}">Explore the preview →</a>@endif</section>
        </div>
    </div>
</x-filament-panels::page>
