<x-filament-panels::page>
    <div class="adz-portal">
        @if ($customer)
            <a class="adz-portal-link" href="{{ static::getUrl() }}">← All customers</a>
            <section class="adz-portal-hero"><span class="adz-portal-status">{{ $customer->hasVerifiedEmail() ? 'Activated' : 'Awaiting activation' }}</span><h2 class="adz-portal-heading">{{ $customer->name }}</h2><p>{{ $customer->email }}</p><p class="adz-portal-muted">{{ $customer->hasVerifiedEmail() ? 'This customer can sign in to follow their work and conversations.' : 'The customer needs to open their activation email and choose a password. You can assign work before activation.' }}</p></section>
            @if (\App\Support\PortalAccess::staff(auth()->user(), \App\Enums\PortalCapability::ViewWork))
                <section class="adz-portal-section"><div class="adz-portal-row"><h2 class="adz-portal-section-title">Projects & services</h2>@if (\App\Support\PortalAccess::staff(auth()->user(), \App\Enums\PortalCapability::ManageWork))<x-filament::button tag="a" :href="\App\Filament\Pages\Portal\ClientWork::getUrl(['customer' => $customer->id])">Assign project or service</x-filament::button>@endif</div>
                <div class="adz-portal-case-list">@forelse ($works as $work)<a class="adz-portal-card adz-portal-row" href="{{ \App\Filament\Pages\Portal\ClientWork::getUrl(['record' => $work->public_id]) }}"><strong>{{ $work->title }}</strong><span class="adz-portal-status" data-status="{{ $work->status }}">{{ \App\Support\PortalOptions::label($work->status, true) }}</span></a>@empty<p class="adz-portal-card">No work assigned yet. Add the first project or service for this customer.</p>@endforelse</div><p class="adz-portal-muted">Latest 50 work records shown. Search Projects & services for older work.</p></section>
            @endif
        @else
            <p class="adz-portal-intro">Create a customer, send their activation invitation, then assign projects and services. Customers choose their own passwords.</p>
            <div class="adz-portal-search"><label for="customer-search">Search name or email</label><x-filament::input.wrapper><x-filament::input id="customer-search" type="search" wire:model.live.debounce.350ms="search" maxlength="100" /></x-filament::input.wrapper></div>
            <div class="adz-portal-case-list">@forelse ($customers as $item)<a class="adz-portal-card adz-portal-row" href="{{ static::getUrl(['record' => $item->id]) }}"><span><strong>{{ $item->name }}</strong><br><span class="adz-portal-muted">{{ $item->email }}</span></span><span class="adz-portal-status">{{ $item->hasVerifiedEmail() ? 'Activated' : 'Awaiting activation' }}</span></a>@empty<div class="adz-portal-card">{{ $search ? 'No matching customers.' : 'Add your first customer to begin.' }}</div>@endforelse</div>
            @include('filament.portal.pagination', ['pagination' => ['page' => $customers->currentPage(), 'last_page' => $customers->lastPage(), 'total' => $customers->total()]])
        @endif
    </div>
</x-filament-panels::page>
