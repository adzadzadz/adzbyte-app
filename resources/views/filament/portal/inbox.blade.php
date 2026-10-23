<div class="adz-portal" data-portal-support x-data="{}"
    x-on:livewire:navigate.window="if ($wire.replyBody.trim() && ! confirm('Leave this page and discard your unsent reply?')) $event.preventDefault()"
    x-on:beforeunload.window="if ($wire.replyBody.trim()) { $event.preventDefault(); $event.returnValue = ''; }">
    <div class="adz-portal-search"><label for="case-search-{{ $this->getId() }}">Search requests and reports</label><x-filament::input.wrapper><x-filament::input id="case-search-{{ $this->getId() }}" type="search" wire:model.live.debounce.350ms="search" maxlength="100" /></x-filament::input.wrapper></div>
    @include('filament.portal.filters', ['cases' => true])
    @include('filament.portal.feedback')
    @if (trim($this->replyBody) !== '' && $this->replyReference !== $record)
        <div class="adz-portal-callout" role="status"><p>Your unsent reply is kept with its original conversation.</p>
            <x-filament::button color="gray" wire:click="restoreReplyConversation">Return to my draft</x-filament::button>
            <x-filament::button color="gray" wire:click="discardReplyDraft" wire:confirm="Discard your unsent reply?">Discard draft</x-filament::button>
        </div>
    @endif
    <p role="status" aria-live="polite" class="adz-portal-muted">{{ $listing['pagination']['total'] }} matching conversations</p>
    <div class="adz-portal-case-list" wire:loading.class="opacity-60">
        @forelse ($listing['items'] as $item)
            <article class="adz-portal-case-row" wire:key="case-{{ $item['public_id'] }}">
                <button type="button" class="adz-portal-case-toggle" wire:click="{{ $record === $item['public_id'] ? 'closeConversation' : "openConversation('{$item['public_id']}')" }}" aria-expanded="{{ $record === $item['public_id'] ? 'true' : 'false' }}" aria-controls="conversation-{{ $item['public_id'] }}">
                    <span><span class="adz-portal-kicker">{{ ucfirst($item['type']) }}{{ $staff ? ' · '.$item['customer'] : '' }}{{ $item['unread'] ? ' · New update' : '' }}</span><strong>{{ $item['subject'] }}</strong><span class="adz-portal-muted">{{ $item['work']['title'] ?? 'General / account request' }}{{ $staff ? ' · '.ucfirst($item['priority']).' priority · '.($item['assignee'] ?? 'Unassigned') : '' }} · {{ \Illuminate\Support\Carbon::parse($item['updated_at'])->format('M j, Y') }}</span></span>
                    <span class="adz-portal-status" data-status="{{ $item['status'] }}">{{ \App\Support\PortalOptions::label($item['status'], $staff) }}</span><span aria-hidden="true">{{ $record === $item['public_id'] ? '−' : '+' }}</span>
                </button>
                @if ($case && $record === $item['public_id'])<div id="conversation-{{ $item['public_id'] }}" class="adz-portal-expanded">@include('filament.portal.conversation')</div>@endif
            </article>
        @empty
            @unless ($case)<section class="adz-portal-card"><h3 class="adz-portal-title">{{ $this->hasListFilters() ? 'No matching conversations' : 'No requests or reports yet' }}</h3><p class="adz-portal-muted">{{ $this->hasListFilters() ? 'Clear your search and filters to see all conversations.' : ($staff ? 'Customer requests and reports will appear here.' : 'Start a request or report when you need help. Replies and updates will stay together here.') }}</p></section>@endunless
        @endforelse
    </div>
    @if ($case && ! collect($listing['items'])->contains('public_id', $case['public_id']))<div class="adz-portal-expanded"><x-filament::button color="gray" wire:click="closeConversation">Close conversation</x-filament::button>@include('filament.portal.conversation')</div>@endif
    @include('filament.portal.pagination', ['pagination' => $listing['pagination']])
</div>
