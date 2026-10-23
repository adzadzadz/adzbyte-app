<x-filament-panels::page>
    <div class="adz-portal" data-portal-work>
        @if ($work)
            <a wire:navigate class="adz-portal-link" href="{{ static::getUrl([...$this->listLocation(), 'page' => $this->listPage, 'listPage' => null]) }}">← All {{ $staff ? 'projects & services' : ($workType === 'project' ? 'projects' : 'services') }}</a>
            <section class="adz-portal-hero" aria-labelledby="work-heading">
                <div class="adz-portal-row"><span class="adz-portal-kicker">{{ ucfirst($work['type']) }}{{ $staff ? ' · '.$work['customer'] : '' }}</span><span class="adz-portal-status" data-status="{{ $work['status'] }}">{{ \App\Support\PortalOptions::label($work['status'], $staff) }}</span></div>
                <h2 id="work-heading" class="adz-portal-heading">{{ $work['title'] }}</h2>
                <x-portal-text :text="$work['summary']" />
                <dl class="adz-portal-facts"><div><dt>Target date</dt><dd>{{ $work['target_date'] ? \Illuminate\Support\Carbon::parse($work['target_date'])->format('M j, Y') : 'To be confirmed' }}</dd></div><div><dt>Your contact</dt><dd>{{ $work['assigned_to'] ?? 'Adzbyte Solutions team' }}</dd></div></dl>
                @if ($work['next_step'])<div class="adz-portal-callout" role="note"><h3>Next step</h3><x-portal-text :text="$work['next_step']" /></div>@endif
                @unless ($staff)
                    <x-filament::button tag="a" href="#work-conversations" icon="heroicon-o-chat-bubble-left-right">Requests, reports & replies</x-filament::button>
                @endunless
            </section>
            <div class="adz-portal-grid">
                <section class="adz-portal-card" aria-labelledby="milestones-heading">
                    <h3 id="milestones-heading" class="adz-portal-title">Milestones</h3>
                    @if ($work['milestone_count'])
                        <p class="adz-portal-muted">{{ $work['completed_milestone_count'] }} of {{ $work['milestone_count'] }} complete</p>
                        <ol class="adz-portal-milestones">@foreach ($work['milestones'] as $milestone)<li><span class="adz-portal-milestone-icon" aria-hidden="true">{{ $milestone['completed_at'] ? '✓' : '○' }}</span><div><h4>{{ $milestone['title'] }}</h4><p class="adz-portal-muted">{{ $milestone['completed_at'] ? 'Completed '.\Illuminate\Support\Carbon::parse($milestone['completed_at'])->format('M j, Y') : ($milestone['target_date'] ? 'Target: '.\Illuminate\Support\Carbon::parse($milestone['target_date'])->format('M j, Y') : 'Upcoming') }}</p>@if ($staff && \App\Support\PortalAccess::staff(auth()->user(), \App\Enums\PortalCapability::ManageWork))<x-filament::button color="gray" size="sm" wire:click="mountAction('milestone', { milestone: '{{ $milestone['public_id'] }}' })">Edit milestone</x-filament::button>@endif</div></li>@endforeach</ol>
                    @else<p class="adz-portal-muted">Your team will add milestones as the plan takes shape. Any next step is shown above.</p>@endif
                </section>
                <section class="adz-portal-card" aria-labelledby="updates-heading">
                    <h3 id="updates-heading" class="adz-portal-title">Updates from your team</h3>
                    <div class="adz-portal-thread">@forelse ($work['updates'] as $update)<article class="adz-portal-message"><p class="adz-portal-muted">{{ $update['author'] }} · <time datetime="{{ $update['created_at'] }}">{{ \Illuminate\Support\Carbon::parse($update['created_at'])->timezone('Asia/Manila')->format('M j, Y · g:i a') }} PHT</time></p><x-portal-text :text="$update['body']" /></article>@empty<p class="adz-portal-muted">No updates yet. Your team will post progress here.</p>@endforelse</div>
                    @include('filament.portal.pagination', ['pagination' => $work['pagination'], 'label' => 'Update'])
                </section>
            </div>
            @if (! $staff || \App\Support\PortalAccess::staff(auth()->user(), \App\Enums\PortalCapability::ViewCases))
                @if ($staff)
                    <livewire:portal.staff-support-inbox :work="$work['public_id']" :key="'staff-support-'.$work['public_id']" />
                @else
                    <livewire:portal.support-inbox :work="$work['public_id']" :key="'support-'.$work['public_id']" />
                @endif
            @endif
            @if ($staff)<details class="adz-portal-card"><summary>Recent activity history</summary><ol class="adz-portal-history">@foreach ($work['history'] as $event)<li>{{ ucfirst(str_replace('_', ' ', $event['action'])) }} · {{ $event['actor'] }} · {{ \Illuminate\Support\Carbon::parse($event['created_at'])->timezone('Asia/Manila')->format('M j, g:i a') }}</li>@endforeach</ol><p class="adz-portal-muted">Latest 50 events shown. The complete history is retained.</p></details>@endif
        @else
            <p class="adz-portal-intro">{{ $staff ? 'Manage customer work, milestones and progress updates independently of checkout.' : 'Follow progress, see what is coming next, and keep your team in the loop.' }}</p>
            <div class="adz-portal-search"><label for="work-search">Search {{ $staff ? 'projects and services' : ($workType === 'project' ? 'projects' : 'services') }}</label><x-filament::input.wrapper><x-filament::input id="work-search" type="search" wire:model.live.debounce.350ms="search" maxlength="100" /></x-filament::input.wrapper></div>
            @include('filament.portal.filters', ['cases' => false])
            @include('filament.portal.feedback')
            <p role="status" aria-live="polite" class="adz-portal-muted">{{ $listing['pagination']['total'] }} matching records</p>
            <div class="adz-portal-grid" wire:loading.class="opacity-60">@forelse ($listing['items'] as $item)@include('filament.portal.work-card', ['item' => $item, 'staff' => $staff, 'listContext' => $this->listLocation()])@empty<div class="adz-portal-card adz-portal-empty"><h2 class="adz-portal-title">{{ $this->hasListFilters() ? 'No matching work' : ($staff ? 'Add your first customer project or service' : 'No '.($workType === 'project' ? 'projects' : 'services').' yet') }}</h2><p class="adz-portal-muted">{{ $this->hasListFilters() ? 'Try another search or clear the field to see all records.' : ($staff ? 'Use Add project or service to connect work to an existing customer account.' : 'Your team will add your work here, including its status, milestones and next steps.') }}</p>@unless ($staff)<a wire:navigate class="adz-portal-link" href="{{ \App\Filament\Account\Pages\Support::getUrl(panel: 'account') }}">Contact your team</a>@endunless</div>@endforelse</div>
            @include('filament.portal.pagination', ['pagination' => $listing['pagination']])
        @endif
    </div>
</x-filament-panels::page>
