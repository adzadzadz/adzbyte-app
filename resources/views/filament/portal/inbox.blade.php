<div class="adz-portal" data-portal-support>
    <div class="adz-portal-search"><label for="case-search-{{ $this->getId() }}">Search requests and reports</label><x-filament::input.wrapper><x-filament::input id="case-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.350ms="search" maxlength="100" /></x-filament::input.wrapper></div>
    <div class="adz-portal-case-list" wire:loading.class="opacity-60">
        @forelse ($listing['items'] as $item)
            <article class="adz-portal-case-row" wire:key="case-{{ $item['public_id'] }}">
                <button type="button" class="adz-portal-case-toggle" wire:click="{{ $record === $item['public_id'] ? 'closeConversation' : "openConversation('{$item['public_id']}')" }}" aria-expanded="{{ $record === $item['public_id'] ? 'true' : 'false' }}" aria-controls="conversation-{{ $item['public_id'] }}">
                    <span><span class="adz-portal-kicker">{{ ucfirst($item['type']) }}{{ $staff ? ' · '.$item['customer'] : '' }}{{ $item['unread'] ? ' · New update' : '' }}</span><strong>{{ $item['subject'] }}</strong><span class="adz-portal-muted">{{ $item['work']['title'] ?? 'General / account request' }} · {{ \Illuminate\Support\Carbon::parse($item['updated_at'])->format('M j, Y') }}</span></span>
                    <span class="adz-portal-status" data-status="{{ $item['status'] }}">{{ \App\Support\PortalOptions::label($item['status'], $staff) }}</span><span aria-hidden="true">{{ $record === $item['public_id'] ? '−' : '+' }}</span>
                </button>
                @if ($case && $record === $item['public_id'])<div id="conversation-{{ $item['public_id'] }}" class="adz-portal-expanded">@include('filament.portal.conversation')</div>@endif
            </article>
        @empty
            <section class="adz-portal-card"><h3 class="adz-portal-title">{{ $search ? 'No matching conversations' : 'No requests or reports yet' }}</h3><p class="adz-portal-muted">{{ $search ? 'Clear your search to see all conversations.' : 'Start a request or report when you need help. Replies and updates will stay together here.' }}</p></section>
        @endforelse
    </div>
    @if ($case && ! collect($listing['items'])->contains('public_id', $case['public_id']))<div class="adz-portal-expanded">@include('filament.portal.conversation')</div>@endif
    @include('filament.portal.pagination', ['pagination' => $listing['pagination']])
</div>
